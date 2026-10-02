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
            'latest_message' => $latest === null ? null : self::message($latest),
        ];
    }

    /**
     * The room shape for the thread page, which additionally carries the payment
     * panel.
     *
     * Split from `room()` on purpose. `payment()` reads `highestBid()` and scans
     * the room's messages, so putting it in the list shape would make the
     * inbox two extra queries per row. The panel only ever renders on one thread
     * at a time, so it is paid for once.
     *
     * @return array<string, mixed>
     */
    public static function thread(ChatRoom $room, User $viewer): array
    {
        return [
            ...self::room($room, $viewer),
            // Null for support and credential rooms: money only moves where the
            // admin can see it.
            'payment' => self::payment($room, $viewer),
        ];
    }

    /**
     * The message shape used by the thread view and the real-time payload.
     *
     * `kind` is what the thread renders by rather than by sniffing the body, and
     * `proof_url` is null unless the kind actually carries a file.
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
            'sender_name' => $message->sender?->name,
            'sender_role' => $message->senderRole()->value,
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  iterable<int, ChatMessage>  $messages
     * @return array<int, array<string, mixed>>
     */
    public static function messages(iterable $messages): array
    {
        $presented = [];

        foreach ($messages as $message) {
            $presented[] = self::message($message);
        }

        return $presented;
    }

    /**
     * The payment panel for an auction group thread, or null for every other
     * room kind.
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
            'qris' => ChatConfig::qrisConfigured() ? [
                'image_url' => ChatConfig::qrisImagePath(),
                'account_name' => ChatConfig::qrisAccountName(),
                'account_number' => ChatConfig::qrisAccountNumber(),
            ] : null,
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
            return $role === ChatParticipantRole::Admin
                && ChatConfig::qrisConfigured()
                && $auction->isEnded()
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
