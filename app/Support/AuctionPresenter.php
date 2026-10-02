<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Auction;
use App\Models\AuctionScreenshot;

/**
 * Shapes Eloquent auctions into the arrays the Inertia pages consume.
 *
 * Keeping this in one place means the storefront cards and the detail page
 * can never disagree about how a price or a screenshot URL is built.
 */
final class AuctionPresenter
{
    /**
     * The compact shape used by listing grids.
     *
     * @return array<string, mixed>
     */
    public static function summary(Auction $auction): array
    {
        return [
            'uuid' => $auction->uuid,
            'title' => $auction->title,
            'status' => $auction->status->value,
            'starting_price' => $auction->starting_price,
            'current_price' => $auction->current_price,
            'price' => AuctionConfig::price($auction->effectivePrice()),
            'starting_price_label' => AuctionConfig::price($auction->starting_price),
            'current_price_label' => $auction->current_price !== null
                ? AuctionConfig::price($auction->current_price)
                : null,
            'ends_at' => $auction->ends_at?->toIso8601String(),
            'ended_at' => $auction->ended_at?->toIso8601String(),
            'ended_reason' => $auction->ended_reason?->value,
            'screenshot' => self::screenshotUrl($auction->screenshots->first()),
            'seller_name' => $auction->seller->name,
        ];
    }

    /**
     * The full shape used by the detail page.
     *
     * @return array<string, mixed>
     */
    public static function detail(Auction $auction): array
    {
        return [
            ...self::summary($auction),
            'description' => $auction->description,
            'winner_name' => $auction->winner?->name,
            'reserve_price' => $auction->reserve_price,
            'reserve_price_label' => $auction->reserve_price !== null
                ? AuctionConfig::price($auction->reserve_price)
                : null,
            'starts_at' => $auction->starts_at?->toIso8601String(),
            'created_at' => $auction->created_at?->toIso8601String(),
            'screenshots' => $auction->screenshots
                ->map(fn (AuctionScreenshot $screenshot): array => [
                    'url' => $screenshot->url(),
                    'position' => $screenshot->position,
                ])
                ->values()
                ->all(),
        ];
    }

    private static function screenshotUrl(?AuctionScreenshot $screenshot): ?string
    {
        return $screenshot?->url();
    }
}
