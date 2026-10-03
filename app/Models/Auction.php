<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidRouteKey;
use App\Concerns\Searchable;
use App\Enums\AuctionStatus;
use App\Enums\ChatParticipantRole;
use App\Enums\ChatRoomType;
use App\Enums\CloseReason;
use App\Support\AuctionConfig;
use Database\Factories\AuctionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $seller_id
 * @property int|null $winner_id
 * @property string $title
 * @property string $description
 * @property int $starting_price
 * @property int|null $current_price
 * @property int|null $reserve_price
 * @property AuctionStatus $status
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property Carbon|null $ended_at
 * @property CloseReason|null $ended_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $seller
 * @property-read User|null $winner
 * @property-read Collection<int, AuctionScreenshot> $screenshots
 * @property-read Collection<int, Bid> $bids
 * @property-read Bid|null $highestBid
 */
#[Fillable(['title', 'description', 'starting_price', 'reserve_price'])]
class Auction extends Model
{
    /** @use HasFactory<AuctionFactory> */
    use HasFactory, HasUuidRouteKey, Searchable;

    /**
     * The statuses a non-owner may see on the public storefront.
     *
     * @var array<int, AuctionStatus>
     */
    public const PUBLIC_STATUSES = [
        AuctionStatus::Active,
        AuctionStatus::Ended,
        AuctionStatus::Paid,
    ];

    /**
     * The public statuses as raw column values, ready for `whereIn`.
     *
     * @return array<int, string>
     */
    public static function publicStatuses(): array
    {
        return array_map(
            static fn (AuctionStatus $status): string => $status->value,
            self::PUBLIC_STATUSES,
        );
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AuctionStatus::class,
            'starting_price' => 'integer',
            'current_price' => 'integer',
            'reserve_price' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'ended_at' => 'datetime',
            'ended_reason' => CloseReason::class,
        ];
    }

    /**
     * The bid that wins the auction: highest amount, ties broken by the earliest
     * bid.
     *
     * Expressed as a relation rather than a query method so both the seller list and
     * the close action resolve the winner the same way, and so the seller list can
     * eager-load it in one query.
     *
     * @return HasOne<Bid, $this>
     */
    public function highestBid(): HasOne
    {
        return $this->hasOne(Bid::class)->ofMany([
            'amount' => 'max',
            'id' => 'min',
        ], 'max');
    }

    /**
     * The user who listed the auction.
     *
     * @return BelongsTo<User, $this>
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /**
     * The winning bidder, recorded once the auction closes.
     *
     * @return BelongsTo<User, $this>
     */
    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_id');
    }

    /**
     * The account proof images shown on the public storefront.
     *
     * @return HasMany<AuctionScreenshot, $this>
     */
    public function screenshots(): HasMany
    {
        return $this->hasMany(AuctionScreenshot::class)->orderBy('position');
    }

    /**
     * The bids placed on this auction, newest first.
     *
     * @return HasMany<Bid, $this>
     */
    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class)->latest('id');
    }

    /**
     * The payment trail for this auction, if one has been started.
     *
     * A `HasOne` rather than a method that runs a query, so the thread page can
     * eager-load it alongside the room instead of asking per message.
     *
     * @return HasOne<AuctionPayment, $this>
     */
    public function payment(): HasOne
    {
        return $this->hasOne(AuctionPayment::class);
    }

    /**
     * The winning amount plus the flat admin fee, for a new invoice.
     *
     * Read from `highestBid()`, never from `current_price`: the cache is written
     * by `PlaceBid` and can drift, and this number goes on a receipt.
     *
     * Null when there is no highest bid to charge for.
     */
    public function paymentAmount(): ?int
    {
        $amount = $this->highestBid?->amount;

        return $amount === null ? null : $amount + AuctionConfig::adminFeeFlat();
    }

    /**
     * One of this auction's threads, by kind.
     *
     * The two kinds are separate rows rather than a flag on one room, so this
     * takes the `ChatRoomType` instead of returning "the" room. Null until
     * somebody opens the thread — `StartChat` creates them lazily, and an
     * auction nobody talks about should not leave an empty room behind.
     *
     * @return HasOne<ChatRoom, $this>
     */
    public function chatRoom(ChatRoomType $type): HasOne
    {
        return $this->hasOne(ChatRoom::class)->where('type', $type->value);
    }

    /**
     * Restrict to auctions visible on the public storefront.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->whereIn('status', self::publicStatuses());
    }

    /**
     * Restrict to auctions currently accepting bids.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', AuctionStatus::Active->value);
    }

    public function isDraft(): bool
    {
        return $this->status === AuctionStatus::Draft;
    }

    public function isActive(): bool
    {
        return $this->status === AuctionStatus::Active;
    }

    /**
     * Whether the auction is won but not yet paid out.
     *
     * This is the payout window: `Ended` means a winner exists and the money has
     * not moved yet, `Paid` means the seller confirmed receipt. Cancelling
     * deliberately does not count — that path has no winner to pay.
     */
    public function isEnded(): bool
    {
        return $this->status === AuctionStatus::Ended;
    }

    /**
     * Whether the auction is live and still inside its bidding window.
     */
    public function isOpen(): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        return $this->ends_at === null || $this->ends_at->isFuture();
    }

    /**
     * Why a seller may not close this auction early right now, if they may not.
     *
     * This is a business rule, not an authorization question, so it returns a
     * translation key instead of throwing: the UI shows the same reason next to
     * the disabled button that the endpoint rejects with. `null` means closing
     * early is allowed.
     *
     * The three ways it can be blocked:
     *  - the auction is not live, so there is nothing to close;
     *  - bids exist but sit below the seller's reserve, so accepting one now
     *    would take the first-minute lowball bid;
     *  - the configured anti-snipe window is still open because a bid landed
     *    moments ago, giving the runner-up a chance to raise.
     */
    public function earlyCloseBlockedReason(): ?string
    {
        if (! $this->isActive()) {
            return 'auctions.early_close.errors.not_active';
        }

        // Read the highest bid off the relation rather than `current_price`.
        // `current_price` is a denormalised cache maintained by PlaceBid, so
        // trusting it here would let a drifted row let a below-reserve bid
        // through.
        $highest = $this->highestBid?->amount;

        if ($this->reserve_price !== null
            && ($highest === null || $highest < $this->reserve_price)) {
            return 'auctions.early_close.errors.below_reserve';
        }

        $minutes = AuctionConfig::minimumMinutesSinceLastBid();

        if ($minutes > 0) {
            $lastBidAt = $this->bids()->max('created_at');

            if ($lastBidAt !== null
                && Carbon::parse($lastBidAt)->addMinutes($minutes)->isFuture()) {
                return 'auctions.early_close.errors.too_recent_bid';
            }
        }

        return null;
    }

    /**
     * Whether a seller may close this auction early and hand it to the highest
     * bidder on the spot.
     */
    public function canEndEarly(User $user): bool
    {
        return $user->id === $this->seller_id
            && $this->earlyCloseBlockedReason() === null;
    }

    /**
     * The role a user holds in one of this auction's chat rooms.
     *
     * An auction runs two threads and they are not the same audience: the group
     * room has the admin in it, the credential room does not. Both exist only
     * once there is a winner, since before that there is nobody to hand the
     * account over to.
     *
     * This is the single source of truth for thread membership.
     * `ChatRoom::roleFor()` delegates here, `StartChatController` uses it to
     * authorise, and the storefront buttons ask it which label to show — so the
     * button, the route, the page and the broadcast channel cannot disagree
     * about who is in which thread.
     */
    public function chatRoleFor(ChatRoomType $type, User $user): ?ChatParticipantRole
    {
        // No winner means nobody to hand the account to, so there is no thread
        // to join — including for admins.
        if ($this->winner_id === null) {
            return null;
        }

        if ($user->id === $this->seller_id) {
            return ChatParticipantRole::Seller;
        }

        if ($user->id === $this->winner_id) {
            return ChatParticipantRole::Winner;
        }

        // Admins are in every thread except the credential handoff, where they
        // would be able to read the account details.
        return $user->is_admin && $type->admitsAdmin()
            ? ChatParticipantRole::Admin
            : null;
    }

    /**
     * Whether this user takes part in the given auction thread.
     */
    public function hasChatParticipant(ChatRoomType $type, User $user): bool
    {
        return $this->chatRoleFor($type, $user) !== null;
    }

    /**
     * The price shown to buyers: the current bid, or the opening price.
     */
    public function effectivePrice(): int
    {
        return $this->current_price ?? $this->starting_price;
    }

    /**
     * The smallest amount the next bid may be.
     *
     * The opening bid only has to reach the starting price; every bid after
     * that must clear the current bid by the configured increment.
     */
    public function minimumNextBid(): int
    {
        if ($this->current_price === null) {
            return $this->starting_price;
        }

        return $this->current_price + AuctionConfig::minimumBidIncrement();
    }
}
