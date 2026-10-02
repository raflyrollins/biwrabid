<?php

declare(strict_types=1);

namespace App\Actions;

use App\Events\BidPlaced;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\User;
use App\Support\AuctionConfig;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PlaceBid
{
    /**
     * Place a bid inside a locked transaction.
     *
     * The row lock serialises concurrent bids on the same auction, so the
     * minimum-amount guard can never be raced: the second writer reads the
     * first writer's `current_price`.
     *
     * @throws ValidationException
     */
    public function __invoke(User $bidder, Auction $auction, int $amount): Bid
    {
        return DB::transaction(function () use ($bidder, $auction, $amount): Bid {
            /** @var Auction $locked */
            $locked = Auction::query()
                ->whereKey($auction->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->guard($locked, $bidder, $amount);

            $bid = $locked->bids()->create([
                'bidder_id' => $bidder->id,
                'amount' => $amount,
            ]);

            $locked->forceFill(['current_price' => $amount])->save();

            $bid->setRelation('bidder', $bidder);
            $bid->setRelation('auction', $locked);

            BidPlaced::dispatch($locked, $bid);

            return $bid;
        });
    }

    /**
     * @throws ValidationException
     */
    private function guard(Auction $auction, User $bidder, int $amount): void
    {
        if (! $auction->isOpen()) {
            throw ValidationException::withMessages([
                'amount' => __('ui.auctions.bids.errors.closed'),
            ]);
        }

        if ($auction->seller_id === $bidder->id) {
            throw ValidationException::withMessages([
                'amount' => __('ui.auctions.bids.errors.own'),
            ]);
        }

        $minimum = $auction->minimumNextBid();

        if ($amount < $minimum) {
            throw ValidationException::withMessages([
                'amount' => __('ui.auctions.bids.errors.too_low', [
                    'amount' => AuctionConfig::price($minimum),
                ]),
            ]);
        }
    }
}
