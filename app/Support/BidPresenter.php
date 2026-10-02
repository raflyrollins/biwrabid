<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Bid;

/**
 * Shapes bids into the arrays the Inertia pages and broadcast payloads use.
 */
final class BidPresenter
{
    /**
     * The compact shape used by the bid list and the real-time payload.
     *
     * @return array<string, mixed>
     */
    public static function summary(Bid $bid): array
    {
        $bid->loadMissing('bidder');

        return [
            'id' => $bid->id,
            'bidder_name' => $bid->bidder->name,
            'amount' => $bid->amount,
            'amount_label' => AuctionConfig::price($bid->amount),
            'created_at' => $bid->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  iterable<int, Bid>  $bids
     * @return array<int, array<string, mixed>>
     */
    public static function collection(iterable $bids): array
    {
        $presented = [];

        foreach ($bids as $bid) {
            $presented[] = self::summary($bid);
        }

        return $presented;
    }
}
