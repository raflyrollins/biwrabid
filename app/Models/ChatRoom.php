<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidRouteKey;
use App\Enums\ChatParticipantRole;
use App\Enums\ChatRoomType;
use Carbon\CarbonImmutable;
use Database\Factories\ChatRoomFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A conversation thread.
 *
 * Two kinds exist, and membership is derived rather than stored so a room can
 * never end up with a participant list that disagrees with the auction:
 *
 * - `support` — the member who opened it, plus every admin.
 * - `auction` — the auction's seller and recorded winner, plus every admin.
 *
 * @property int $id
 * @property string $uuid
 * @property ChatRoomType $type
 * @property int|null $auction_id
 * @property int|null $initiator_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['type', 'auction_id', 'initiator_id'])]
class ChatRoom extends Model
{
    /** @use HasFactory<ChatRoomFactory> */
    use HasFactory, HasUuidRouteKey;

    /**
     * Per-instance memo for `participantIds()`.
     *
     * @var array<int, int>|null
     */
    private ?array $participant_ids = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ChatRoomType::class,
        ];
    }

    /**
     * The auction whose credential thread this is, if any.
     *
     * @return BelongsTo<Auction, $this>
     */
    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
    }

    /**
     * The member who opened a support thread, if any.
     *
     * @return BelongsTo<User, $this>
     */
    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiator_id');
    }

    /**
     * The thread's messages, oldest first so it reads top to bottom.
     *
     * @return HasMany<ChatMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    /**
     * Every read receipt written against this room's messages.
     *
     * Reached through `chat_message_id` rather than carrying a `chat_room_id` of
     * its own, so a receipt can never disagree with the message it points at.
     *
     * @return HasManyThrough<ChatMessageRead, ChatMessage, $this>
     */
    public function reads(): HasManyThrough
    {
        return $this->hasManyThrough(
            ChatMessageRead::class,
            ChatMessage::class,
            'chat_room_id',
            'chat_message_id',
        );
    }

    public function isSupport(): bool
    {
        return $this->type === ChatRoomType::Support;
    }

    /**
     * The admin-visible thread for an auction: seller, winner and admin.
     */
    public function isGroupRoom(): bool
    {
        return $this->type === ChatRoomType::Group;
    }

    /**
     * The seller <-> winner credential handoff, which the admin cannot enter.
     */
    public function isAuctionRoom(): bool
    {
        return $this->type === ChatRoomType::Auction;
    }

    /**
     * Whether this kind of room has the platform admin in it.
     */
    public function admitsAdmin(): bool
    {
        return $this->type->admitsAdmin();
    }

    /**
     * How the given user relates to this room, or null if they are not a
     * participant and not an admin.
     *
     * This is the single source of truth for "may this user see the room":
     * the policy, the HTTP controller and the broadcast channel-authorisation
     * callback all defer to it, so a private channel can never be broader
     * than the page that links to it.
     *
     * Admin access is type-dependent. The group room includes the admin; the
     * credential room does not, so an admin asking for the seller/winner
     * handoff is refused the same way a stranger would be.
     */
    public function roleFor(User $user): ?ChatParticipantRole
    {
        if ($this->isSupport()) {
            if ($user->is_admin) {
                return ChatParticipantRole::Admin;
            }

            return $this->initiator_id === $user->id
                ? ChatParticipantRole::Member
                : null;
        }

        $auction = $this->auction;

        if ($auction === null) {
            return null;
        }

        return $auction->chatRoleFor($this->type, $user);
    }

    public function canAccess(User $user): bool
    {
        return $this->roleFor($user) !== null;
    }

    /**
     * Everyone who can take part in this room, as user ids.
     *
     * The read receipts need this to decide when a message has been seen by
     * *everyone*: a message is only fully read once every other participant has
     * a receipt, and "every other" cannot be counted from the sender alone.
     *
     * Built from the same derived membership as `roleFor()` rather than a stored
     * participant list, so the answer cannot disagree with who is actually in the
     * room — including the admin, who is a participant in two of the three kinds
     * and deliberately not in the credential one. Admins are enumerated here
     * because, unlike the seller and the winner, they are not derivable from the
     * auction; the result is memoised because a thread renders many messages and
     * every one of them asks the same question.
     *
     * @return array<int, int>
     */
    public function participantIds(): array
    {
        if ($this->participant_ids !== null) {
            return $this->participant_ids;
        }

        $ids = [];

        if ($this->isSupport()) {
            $ids[] = $this->initiator_id;
        } else {
            $auction = $this->auction;
            $ids[] = $auction?->seller_id;
            $ids[] = $auction?->winner_id;
        }

        if ($this->admitsAdmin()) {
            $admins = User::query()
                ->where('is_admin', true)
                ->pluck('id')
                ->all();

            foreach ($admins as $admin) {
                $ids[] = (int) $admin;
            }
        }

        return $this->participant_ids = array_values(array_unique(array_filter($ids)));
    }

    /**
     * The participants whose read receipts a message has to collect before it
     * shows a double tick — everyone in the room except whoever sent it.
     *
     * Excludes the sender because they have, by definition, read their own
     * message: waiting for their receipt would leave the double tick permanently
     * out of reach. A deleted account's messages have nobody to wait for, so they
     * count as read by all.
     *
     * @return array<int, int>
     */
    public function expectedReaderIds(?User $sender = null): array
    {
        $ids = $this->participantIds();

        if ($sender === null) {
            return $ids;
        }

        return array_values(array_diff($ids, [$sender->id]));
    }

    /**
     * The auction members in this room other than the viewer.
     *
     * Admins are omitted because there can be many of them and they are not
     * derivable from the auction; the UI header states separately whether an
     * admin is in this kind of room at all, which is false for the credential
     * thread.
     *
     * @return array<int, array{id: int, name: string, role: ChatParticipantRole}>
     */
    public function counterparties(?User $viewer = null): array
    {
        if ($this->isSupport()) {
            return [];
        }

        $auction = $this->auction;

        if ($auction === null) {
            return [];
        }

        $auction->loadMissing(['seller', 'winner']);

        // A list of pairs, not an enum-keyed array: enum cases cannot be used
        // as array keys.
        $people = [
            [ChatParticipantRole::Seller, $auction->seller],
            [ChatParticipantRole::Winner, $auction->winner],
        ];

        $counterparties = [];

        foreach ($people as [$role, $user]) {
            if ($user === null) {
                continue;
            }

            if ($viewer !== null && $user->id === $viewer->id) {
                continue;
            }

            $counterparties[] = [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $role,
            ];
        }

        return $counterparties;
    }

    /**
     * The most recent message, used for the room list preview.
     *
     * Declared as a relation rather than a query method so the room list can
     * eager-load it in one query instead of one per row.
     *
     * @return HasOne<ChatMessage, $this>
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(ChatMessage::class)->latestOfMany('id');
    }
}
