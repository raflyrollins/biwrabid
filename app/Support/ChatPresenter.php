<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\ChatMessageKind;
use App\Enums\ChatParticipantRole;
use App\Enums\ChatRoomType;
use App\Enums\PaymentStatus;
use App\Models\Auction;
use App\Models\AuctionPayment;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\User;

/**
 * Shapes chat rooms and messages into the arrays the Inertia pages and the
 * broadcast payloads use.
 *
 * No Indonesian literals here: titles are composed in the front end from
 * translation keys, because user-facing strings belong in `lang/`.
 *
 * @see RULES.md — "Code and URLs are English; only the UI is Indonesian"
 */
final class ChatPresenter
{
    /**
     * The room shape used by the room list and the thread header.
     *
     * @return array<string, mixed>
     */
    public static function room(ChatRoom $room, User $viewer): array
    {
        // Only what a preview row draws. Nothing here needs the message's files,
        // its proof paths or its read state, and asking for them would put two
        // queries per row on the room list — see `messagePreview()`.
        $room->loadMissing(['auction', 'initiator', 'latestMessage.sender']);
        $latest = $room->latestMessage;
        $auction = $room->auction;

        return [
            'uuid' => $room->uuid,
            'kind' => $room->type->value,
            'role' => $room->roleFor($viewer)?->value,
            // Lets the thread header say "an admin can see this" or not, without
            // the front end having to know which room kinds exclude them.
            'admits_admin' => $room->admitsAdmin(),
            'updated_at' => $latest !== null && $latest->created_at !== null
                ? $latest->created_at->toIso8601String()
                : $room->updated_at?->toIso8601String(),
            'counterparties' => self::counterparties($room, $viewer),
            'auction' => $auction === null ? null : [
                'uuid' => $auction->uuid,
                'title' => $auction->title,
                'status' => $auction->status->value,
                'image_url' => $auction->screenshots()->first()?->url(),
            ],
            'latest_message' => $latest === null ? null : self::messagePreview($latest),
        ];
    }

    /**
     * The one-line shape a room list row draws.
     *
     * Deliberately not `message()`. A preview is not a message: it carries no
     * delivery state and no files, because a list row renders neither. Sending
     * the full shape would mean eager-loading every receipt on the page and
     * resolving each room's participant list — one message's worth of queries per
     * row, on the room list, to fill in fields no row reads. A tick belongs to a
     * thread that is open, where `message()` decides it.
     *
     * @return array<string, mixed>
     */
    public static function messagePreview(ChatMessage $message): array
    {
        $message->loadMissing('sender');

        return [
            'id' => $message->id,
            'body' => $message->body,
            'kind' => $message->kind->value,
            'sender_name' => $message->sender?->name,
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }

    /**
     * The message shape used by the thread view and the real-time payload.
     *
     * `kind` is what the thread renders by rather than by sniffing the body.
     * `proof_url` and `qris_url` are null unless the kind actually carries that
     * file, so ordinary chatter can never surface a path. `attachments` are the
     * files a member attached to a plain message, resolved to their URLs here so
     * the client never builds a path of its own.
     *
     * `sender_id` is what the client matches its own messages against. `role` is
     * not a substitute: two people in the same room can hold the same role, and
     * in a support thread the admin and the member are the only two parties, so a
     * role comparison would put half the thread on the wrong side.
     *
     * @return array<string, mixed>
     */
    public static function message(ChatMessage $message): array
    {
        $message->loadMissing('sender');

        return [
            'id' => $message->id,
            'body' => $message->body,
            'kind' => $message->kind->value,
            'amount' => $message->amount,
            'proof_url' => $message->proofUrl(),
            'qris_url' => $message->qrisUrl(),
            'attachments' => $message->attachmentList(),
            'sender_id' => $message->user_id,
            'sender_name' => $message->sender?->name,
            'sender_role' => $message->senderRole()->value,
            'created_at' => $message->created_at?->toIso8601String(),
            // The double tick. False until every other participant has a receipt,
            // which is decided on the server because it depends on who is in the
            // room — see `ChatMessage::isReadByAll()`.
            'read_by_all' => $message->isReadByAll(),
        ];
    }

    /**
     * @param  iterable<int, ChatMessage>  $messages
     * @param  ChatRoom|null  $room  the room every message came from. Passed in
     *                               rather than read off each message because
     *                               `isReadByAll()` needs the room's participant
     *                               list, and one instance can then be shared by
     *                               the whole page instead of being re-queried per
     *                               message.
     * @return array<int, array<string, mixed>>
     */
    public static function messages(iterable $messages, ?ChatRoom $room = null): array
    {
        $presented = [];

        foreach ($messages as $message) {
            if ($room !== null) {
                $message->setRelation('room', $room);
            }

            $presented[] = self::message($message);
        }

        return $presented;
    }

    /**
     * The payment panel for an auction group thread, or null for every other
     * room kind.
     *
     * Rendered on the auction page rather than inside the thread. The steps are
     * auction state, not conversation, and the panel needs the room uuid only
     * because the payment routes are bound to one — which the caller pairs it
     * with. Keeping it out of the room shape means the thread only ever renders
     * the transcript, so there is no second copy of these buttons to drift from
     * the endpoints that enforce them.
     *
     * `actions` is the set of steps this viewer may take *right now*, derived
     * from their role and the payment's current status. Sending that from the
     * server rather than letting the front end work it out is the point: the
     * action guards the same three things, and a button that disagrees with its
     * endpoint is a button that lies.
     *
     * The step names map onto the routes: `request`, `proof`, `received`,
     * `transfer`, `confirm`.
     *
     * @return array<string, mixed>|null
     */
    public static function payment(ChatRoom $room, User $viewer): ?array
    {
        $room->loadMissing(['auction', 'auction.payment']);

        $auction = $room->auction;

        if ($room->type !== ChatRoomType::Group || $auction === null) {
            return null;
        }

        // Queried rather than read off the dynamic `$auction->payment`
        // property: a relationship caches its result, and this presenter is
        // called again on the same model instance after `sendRequest()` has
        // written the first invoice — which would leave the panel showing
        // "no payment yet" and offering the admin a second invoice.
        $payment = self::currentPayment($auction);
        $role = $room->roleFor($viewer);
        $status = $payment?->status;

        $bidAmount = $auction->highestBid?->amount;
        $invoiced = $payment === null ? null : $payment->amount;

        return [
            'status' => $status?->value,
            // The invoiced total once there is one, otherwise the quote the
            // admin would be invoicing from. Both come from the same
            // calculation, so the number on screen never differs from the number
            // that would be sent.
            'amount' => $invoiced ?? $auction->paymentAmount(),
            'bid_amount' => $bidAmount,
            'admin_fee' => AuctionConfig::adminFeeFlat(),
            'qris' => self::sentQrIs($room, $status),
            // The winner's receipt, which is what the admin vouches against before
            // confirming their own account. Deliberately not the transfer
            // receipt: at the point this is read, that one cannot exist yet.
            'has_proof' => $room->messages()
                ->where('kind', ChatMessageKind::PaymentProof->value)
                ->exists(),
            'actions' => self::paymentActions($status, $role, $auction, $room),
        ];
    }

    /**
     * The QRIS on the invoice, or null before one has been sent.
     *
     * Read off the invoice message rather than from config, so the panel shows
     * the same code the winner was actually asked to pay — which is also what an
     * in-flight payment keeps pointing at after the platform's account changes.
     *
     * @return array{image_url: string, account_name: string|null, account_number: string|null}|null
     */
    private static function sentQrIs(ChatRoom $room, ?PaymentStatus $status): ?array
    {
        if ($status === null) {
            return null;
        }

        /** @var ChatMessage|null $invoice */
        $invoice = $room->messages()
            ->where('kind', ChatMessageKind::PaymentRequest->value)
            ->latest('id')
            ->first();

        $url = $invoice?->qrisUrl();

        if ($url === null) {
            return null;
        }

        return [
            'image_url' => $url,
            'account_name' => ChatConfig::qrisAccountName(),
            'account_number' => ChatConfig::qrisAccountNumber(),
        ];
    }

    /**
     * The auction's payment row, read fresh every time.
     *
     * Deliberately not `$auction->payment`: a relationship caches its result,
     * and this presenter runs again on the same model instance after
     * `sendRequest()` has written the first invoice — which would leave the
     * panel claiming there is no payment yet and offering the admin a second
     * one. Going through the query also keeps the return type honestly
     * nullable, which the relation's static type is not.
     */
    private static function currentPayment(Auction $auction): ?AuctionPayment
    {
        return $auction->payment()->first();
    }

    /**
     * Which payment step this viewer may take, if any.
     *
     * @return array<int, string>
     */
    private static function paymentActions(
        ?PaymentStatus $status,
        ?ChatParticipantRole $role,
        Auction $auction,
        ChatRoom $room,
    ): array {
        // No invoice yet: only the admin may raise one, and only for an auction
        // that has actually ended with a winner to charge.
        if ($status === null) {
            return $role === ChatParticipantRole::Admin && $auction->isEnded()
            ? ['request']
            : [];
        }

        return match ($status) {
            PaymentStatus::Requested => match ($role) {
                ChatParticipantRole::Winner => ['proof'],
                // `markReceived()` refuses without a receipt, so the button is
                // withheld in exactly the same circumstance rather than
                // offering an action the endpoint would reject.
                ChatParticipantRole::Admin => $room->messages()
                    ->where('kind', ChatMessageKind::PaymentProof->value)
                    ->exists() ? ['received'] : [],
                default => [],
            },
            PaymentStatus::Received => $role === ChatParticipantRole::Admin ? ['transfer'] : [],
            PaymentStatus::Transferred => $role === ChatParticipantRole::Seller ? ['confirm'] : [],
            PaymentStatus::Completed => [],
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function counterparties(ChatRoom $room, User $viewer): array
    {
        return array_map(
            static fn (array $person): array => [
                'name' => $person['name'],
                'role' => $person['role']->value,
            ],
            $room->counterparties($viewer),
        );
    }

    /**
     * The role label translation key for a participant role.
     */
    public static function roleKey(ChatParticipantRole $role): string
    {
        return 'chat.roles.'.$role->value;
    }
}
