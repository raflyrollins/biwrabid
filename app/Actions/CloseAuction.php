<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\AuctionStatus;
use App\Enums\CloseReason;
use App\Events\AuctionEnded;
use App\Models\Auction;
use Illuminate\Support\Facades\DB;

final class CloseAuction
{
    /**
     * Close an auction and record its winner.
     *
     * The same action serves both ways an auction stops, so the winner
     * selection and the broadcast can never diverge between them:
     *
     *  - `TimeExpired` waits for the published `ends_at` to pass, and is what the
     *    scheduler runs;
     *  - `SellerEnded` skips the clock entirely because a seller closed it early.
     *
     * Returns null when the auction is not active, which is what makes running
     * the closer twice - or racing it against a seller - safe.
     */
    public function __invoke(Auction $auction, ?CloseReason $reason = null): ?Auction
    {
        $reason ??= CloseReason::TimeExpired;

        return DB::transaction(function () use ($auction, $reason): ?Auction {
            /** @var Auction $locked */
            $locked = Auction::query()
                ->whereKey($auction->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isActive()) {
                return null;
            }

            if ($reason === CloseReason::TimeExpired
                && ($locked->ends_at === null || $locked->ends_at->isFuture())) {
                return null;
            }

            // `highestBid()` is the single definition of "who wins" - highest
            // amount, ties broken by the earliest bid - shared with the seller
            // list so the preview can never disagree with the outcome.
            $winningBid = $locked->highestBid()->with('bidder')->first();

            $locked->forceFill([
                'status' => AuctionStatus::Ended,
                'winner_id' => $winningBid?->bidder_id,
                'ended_at' => now(),
                'ended_reason' => $reason,
            ])->save();

            AuctionEnded::dispatch($locked, $reason);

            return $locked;
        });
    }
}
