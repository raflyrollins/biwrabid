<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Auction;
use App\Models\User;

final class AuctionPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * Public auctions are visible to everyone; drafts only to their seller.
     */
    public function view(?User $user, Auction $auction): bool
    {
        if (! $auction->isDraft()) {
            return true;
        }

        return $user?->id === $auction->seller_id;
    }

    public function create(?User $user): bool
    {
        return $user !== null;
    }

    public function update(User $user, Auction $auction): bool
    {
        return $this->ownsDraft($user, $auction);
    }

    public function delete(User $user, Auction $auction): bool
    {
        return $this->ownsDraft($user, $auction);
    }

    public function publish(User $user, Auction $auction): bool
    {
        return $this->ownsDraft($user, $auction);
    }

    public function cancel(User $user, Auction $auction): bool
    {
        return $user->id === $auction->seller_id && $auction->isActive();
    }

    /**
     * Only the seller may stop an auction early.
     *
     * Whether they may do it *right now* is a business rule rather than an
     * authorization question, so the reserve and anti-snipe checks stay on
     * `Auction::earlyCloseBlockedReason()` and reject with a message instead of
     * a 403.
     */
    public function endEarly(User $user, Auction $auction): bool
    {
        return $user->id === $auction->seller_id && $auction->isActive();
    }

    /**
     * A seller may not bid on their own auction, and no one may bid once the
     * bidding window has closed.
     */
    public function bid(User $user, Auction $auction): bool
    {
        return $auction->isOpen() && $user->id !== $auction->seller_id;
    }

    private function ownsDraft(User $user, Auction $auction): bool
    {
        return $user->id === $auction->seller_id && $auction->isDraft();
    }
}
