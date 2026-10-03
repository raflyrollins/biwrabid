<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\AuctionStatus;
use App\Enums\ChatMessageKind;
use App\Enums\ChatParticipantRole;
use App\Enums\ChatRoomType;
use App\Enums\PaymentStatus;
use App\Events\MessageSent;
use App\Models\Auction;
use App\Models\AuctionPayment;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\User;
use App\Support\ChatConfig;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * The payment trail, coordinated inside the auction's group thread.
 *
 * The admin is an intermediary: the winner pays the admin's account, the admin
 * forwards it to the seller, and the seller confirms. Each arrow is a real
 * transfer, so each has its own evidence and its own step:
 *
 *     1. admin sends the invoice         requested
 *     2. winner uploads their receipt    requested
 *     3. admin confirms it arrived       received
 *     4. admin uploads the payout slip   transferred
 *     5. seller confirms it landed       completed -> Auction::Paid
 *
 * One class rather than five because they share the same guard, and a guard
 * copied five times is a guard that will be correct in four of them. Every
 * method returns the message it posted, or null when the step did not apply.
 * That is what makes a double click, a stale button and two admins racing each
 * other all safe: the payment row is re-read under a lock and only the exact
 * expected transition is applied, so the loser of the race simply does nothing.
 *
 * Nothing here authorises the *user* — that is `ChatRoom::roleFor()`'s job at
 * the edge. The role is re-checked anyway, because an action that moves real
 * money should not depend on its caller having checked.
 */
final class CoordinatePayment
{
    /**
     * Step 1: the admin uploads their QRIS and the amount owed is worked out.
     *
     * The QRIS is the admin's own file rather than one committed to the repository,
     * because it is their receiving account and their call which account to use. The
     * amount is never typed in: it is snapshotted from `Auction::paymentAmount()` —
     * the highest bid plus the flat admin fee — so raising the fee later cannot
     * re-price an invoice the winner is already paying.
     */
    public function sendRequest(ChatRoom $room, User $admin, UploadedFile $qr): ?ChatMessage
    {
        $auction = $this->groupAuction($room);

        if ($auction === null || ! $this->actorIs($room, $admin, ChatParticipantRole::Admin)) {
            return null;
        }

        $amount = $auction->paymentAmount();

        if ($amount === null || ! $auction->isEnded()) {
            return null;
        }

        $path = $this->store($qr);

        if ($path === null) {
            return null;
        }

        try {
            $message = DB::transaction(function () use ($auction, $room, $admin, $amount, $path): ?ChatMessage {
                // One invoice per auction, forever. `updateOrCreate` was the wrong
                // tool here: re-sending must not re-price an invoice the winner is
                // already paying, and it must not drag a payment that has moved on
                // (or completed) back to `requested`. The original request message
                // and its QRIS stay in the transcript, so nothing needs re-sending.
                if (AuctionPayment::query()->where('auction_id', $auction->id)->exists()) {
                    return null;
                }

                AuctionPayment::query()->create([
                    'auction_id' => $auction->id,
                    'amount' => $amount,
                    'status' => PaymentStatus::Requested,
                ]);

                $this->touch($room);

                return $this->post(
                    $room,
                    $admin,
                    ChatMessageKind::PaymentRequest,
                    body: __('ui.chat.payment.messages.request'),
                    amount: $amount,
                    qrisPath: $path,
                );
            });
        } catch (\Throwable $e) {
            $this->discard($path);

            throw $e;
        }

        // The upload is written before the transaction opens, so a refused
        // invoice has to clean up after itself.
        if ($message === null) {
            $this->discard($path);

            return null;
        }

        $this->broadcast($message);

        return $message;
    }

    /**
     * Store an upload, or null when the disk refused it.
     *
     * `store()` reports failure by returning false, and writing that into a path
     * column would produce a message pointing at nothing.
     */
    private function store(UploadedFile $file): ?string
    {
        $path = $file->store(ChatConfig::proofsDirectory(), ChatConfig::proofsDisk());

        return $path === false ? null : $path;
    }

    /**
     * Undo a stored upload.
     *
     * Every upload is written before its transaction opens, so the path has to be
     * cleaned up on both a null result and a throw. A row can then never point at
     * a file that is not there, and a refused step leaves nothing behind.
     */
    private function discard(?string $path): void
    {
        if ($path === null) {
            return;
        }

        Storage::disk(ChatConfig::proofsDisk())->delete($path);
    }

    /**
     * Step 2: the winner uploads their transfer receipt.
     *
     * The file is written before the transaction opens and removed again if the
     * transaction does not produce a message, so a rollback can never leave a
     * row pointing at a path that was never stored.
     */
    public function recordPaymentProof(ChatRoom $room, User $winner, UploadedFile $file): ?ChatMessage
    {
        return $this->storeProof(
            $room,
            $winner,
            $file,
            ChatParticipantRole::Winner,
            expected: PaymentStatus::Requested,
            advanceTo: null,
            kind: ChatMessageKind::PaymentProof,
            body: __('ui.chat.payment.messages.proof'),
        );
    }

    /**
     * Step 3: the admin confirms the money reached their own account.
     *
     * Requires the winner's receipt to already be in the thread. The admin is
     * vouching that they checked the account against something the winner
     * actually sent, so a bare confirm with nothing behind it would be an
     * unverifiable step.
     */
    public function markReceived(ChatRoom $room, User $admin): ?ChatMessage
    {
        $auction = $this->groupAuction($room);

        if ($auction === null || ! $this->actorIs($room, $admin, ChatParticipantRole::Admin)) {
            return null;
        }

        $message = DB::transaction(function () use ($auction, $room, $admin): ?ChatMessage {
            $payment = $this->expectPayment($auction, PaymentStatus::Requested);

            if ($payment === null) {
                return null;
            }

            if (! $room->messages()->where('kind', ChatMessageKind::PaymentProof->value)->exists()) {
                return null;
            }

            $payment->forceFill(['status' => PaymentStatus::Received])->save();

            $this->touch($room);

            return $this->post(
                $room,
                $admin,
                ChatMessageKind::PaymentReceived,
                body: __('ui.chat.payment.messages.received'),
            );
        });

        $this->broadcast($message);

        return $message;
    }

    /**
     * Step 4: the admin uploads proof of paying the seller out.
     */
    public function recordTransferProof(ChatRoom $room, User $admin, UploadedFile $file): ?ChatMessage
    {
        return $this->storeProof(
            $room,
            $admin,
            $file,
            ChatParticipantRole::Admin,
            expected: PaymentStatus::Received,
            advanceTo: PaymentStatus::Transferred,
            kind: ChatMessageKind::TransferProof,
            body: __('ui.chat.payment.messages.transfer'),
        );
    }

    /**
     * Step 5: the seller confirms the payout landed.
     *
     * The only place `AuctionStatus::Paid` is written. The seller is the party
     * the admin debited, so the platform does not decide on their behalf that
     * the money arrived.
     */
    public function confirmPayment(ChatRoom $room, User $seller): ?Auction
    {
        $auction = $this->groupAuction($room);

        if ($auction === null || ! $this->actorIs($room, $seller, ChatParticipantRole::Seller)) {
            return null;
        }

        $message = DB::transaction(function () use ($auction, $room, $seller): ?ChatMessage {
            /** @var Auction $locked */
            $locked = Auction::query()
                ->whereKey($auction->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $payment = $this->expectPayment($locked, PaymentStatus::Transferred);

            if ($payment === null) {
                return null;
            }

            $payment->forceFill(['status' => PaymentStatus::Completed])->save();

            $locked->forceFill(['status' => AuctionStatus::Paid])->save();

            $this->touch($room);

            return $this->post(
                $room,
                $seller,
                ChatMessageKind::PaymentCompleted,
                body: __('ui.chat.payment.messages.completed'),
            );
        });

        if ($message === null) {
            return null;
        }

        $this->broadcast($message);

        return $auction->fresh();
    }

    /**
     * The shared body of both "upload a receipt" steps.
     *
     * Identical except for who may do it, which status it waits on, whether it
     * advances that status, and which message kind it posts — so the part that
     * is easy to get wrong (store, lock, verify, roll back the file) is written
     * once.
     *
     * @param  PaymentStatus|null  $advanceTo  null to leave the status alone
     */
    private function storeProof(
        ChatRoom $room,
        User $actor,
        UploadedFile $file,
        ChatParticipantRole $role,
        PaymentStatus $expected,
        ?PaymentStatus $advanceTo,
        ChatMessageKind $kind,
        string $body,
    ): ?ChatMessage {
        $auction = $this->groupAuction($room);

        if ($auction === null || ! $this->actorIs($room, $actor, $role)) {
            return null;
        }

        $path = $this->store($file);

        if ($path === null) {
            return null;
        }

        // A retry is a normal part of a transfer receipt, but it is still a file
        // upload, so it is bounded rather than unlimited.
        if ($room->messages()->where('kind', $kind->value)->count() >= ChatConfig::maxProofsPerStep()) {
            $this->discard($path);

            return null;
        }

        try {
            $message = DB::transaction(function () use ($auction, $room, $actor, $path, $expected, $advanceTo, $kind, $body): ?ChatMessage {
                $payment = $this->expectPayment($auction, $expected);

                if ($payment === null) {
                    return null;
                }

                if ($advanceTo !== null) {
                    $payment->forceFill(['status' => $advanceTo])->save();
                }

                $this->touch($room);

                return $this->post($room, $actor, $kind, body: $body, proofPath: $path);
            });
        } catch (\Throwable $e) {
            $this->discard($path);

            throw $e;
        }

        if ($message === null) {
            $this->discard($path);

            return null;
        }

        $this->broadcast($message);

        return $message;
    }

    /**
     * The auction this room coordinates, but only for a group room.
     *
     * The group-room restriction is the important half. The credential room has
     * the same two non-admin parties as the group room, so a check that only
     * asked "is this the seller or the winner" would happily run the whole money
     * flow inside the one thread that excludes the admin. Money has to be
     * visible to the admin, so it belongs in the room that has one.
     */
    private function groupAuction(ChatRoom $room): ?Auction
    {
        $room->loadMissing('auction');

        if ($room->type !== ChatRoomType::Group) {
            return null;
        }

        return $room->auction;
    }

    /**
     * The payment row for an auction, but only in the status this step expects.
     *
     * Locked so two admins confirming at the same moment cannot both read
     * `requested` and both write `received`.
     */
    private function expectPayment(Auction $auction, PaymentStatus $expected): ?AuctionPayment
    {
        /** @var AuctionPayment|null $payment */
        $payment = AuctionPayment::query()
            ->where('auction_id', $auction->id)
            ->lockForUpdate()
            ->first();

        if ($payment === null || $payment->status !== $expected) {
            return null;
        }

        return $payment;
    }

    private function actorIs(ChatRoom $room, User $user, ChatParticipantRole $expected): bool
    {
        return $room->roleFor($user) === $expected;
    }

    private function post(
        ChatRoom $room,
        User $author,
        ChatMessageKind $kind,
        string $body,
        ?int $amount = null,
        ?string $proofPath = null,
        ?string $qrisPath = null,
    ): ChatMessage {
        /** @var ChatMessage $message */
        $message = $room->messages()->create([
            'user_id' => $author->id,
            'body' => $body,
            'kind' => $kind,
            'amount' => $amount,
            'proof_path' => $proofPath,
            'qris_path' => $qrisPath,
        ]);

        $message->setRelation('room', $room);

        // A payment message is broadcast the moment it is written and nobody has
        // read it yet, so the double tick is false. Setting the relation answers
        // `isReadByAll()` without a query per step.
        $message->setRelation('reads', new Collection);

        return $message;
    }

    /**
     * Touch the room so the room list orders by latest activity.
     */
    private function touch(ChatRoom $room): void
    {
        $room->forceFill(['updated_at' => now()])->saveQuietly();
    }

    private function broadcast(?ChatMessage $message): void
    {
        // Nullable because a step that loses its race, or finds the room in the
        // wrong state, produces no message — and must not broadcast anything.
        if ($message === null) {
            return;
        }

        MessageSent::dispatch($message);
    }
}
