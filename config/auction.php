<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Auction Duration
    |--------------------------------------------------------------------------
    |
    | Bounds for how long an auction may run once a seller publishes it. The
    | minimum is the binding rule from RULES.md (no hardcoded durations); the
    | default is what the publish form pre-fills.
    |
    */

    'minimum_duration_hours' => (int) env('AUCTION_MINIMUM_DURATION_HOURS', 96),
    'default_duration_hours' => (int) env('AUCTION_DEFAULT_DURATION_HOURS', 168),
    'maximum_duration_hours' => (int) env('AUCTION_MAXIMUM_DURATION_HOURS', 720),

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | All prices are stored as whole rupiah (no minor units). Format them with
    | `Number::currency($value, config('auction.currency'), config('auction.currency_locale'))`.
    |
    */

    'currency' => env('AUCTION_CURRENCY', 'IDR'),
    'currency_locale' => env('AUCTION_CURRENCY_LOCALE', 'id'),

    /*
    |--------------------------------------------------------------------------
    | Starting Price
    |--------------------------------------------------------------------------
    |
    | The smallest price a seller may open an auction at.
    |
    */

    'minimum_starting_price' => (int) env('AUCTION_MINIMUM_STARTING_PRICE', 1000),

    /*
    |--------------------------------------------------------------------------
    | Bidding
    |--------------------------------------------------------------------------
    |
    | `minimum_bid_increment` is how far above the current bid each new bid must
    | land. The opening bid itself only has to reach the starting price. The
    | preview count caps how many recent bids are shipped to the detail page.
    |
    */

    'minimum_bid_increment' => (int) env('AUCTION_MINIMUM_BID_INCREMENT', 10_000),

    'bids' => [
        'preview_count' => (int) env('AUCTION_BID_PREVIEW_COUNT', 8),
    ],

    /*
    |--------------------------------------------------------------------------
    | Early Close
    |--------------------------------------------------------------------------
    |
    | A seller may stop an auction early and hand it to the highest bidder, but
    | only when the bid stands at or above their optional `reserve_price`.
    |
    | `minimum_minutes_since_last_bid` is the anti-snipe guard: with it above
    | zero an auction cannot be closed while a bid is that recent, so a bidder
    | who wins by a hair has a short window to be outbid. Zero disables it,
    | which leaves `reserve_price` as the only protection.
    |
    */

    'minimum_minutes_since_last_bid' => (int) env('AUCTION_MINIMUM_MINUTES_SINCE_LAST_BID', 0),

    /*
    |--------------------------------------------------------------------------
    | Admin Fee
    |--------------------------------------------------------------------------
    |
    | A flat amount the winner pays on top of the winning bid, because the admin
    | receives the payment first and pays the seller out of their own account.
    | Flat rather than a percentage: the buyer is quoted one number up front and
    | it does not move as the bid does.
    |
    | Zero means no fee, which is the honest default for a platform that has not
    | agreed a fee with its users yet. The total is snapshotted onto the invoice
    | message when it is sent, so raising this never re-prices an outstanding
    | request.
    |
    */

    'admin_fee_flat' => (int) env('AUCTION_ADMIN_FEE_FLAT', 0),

    /*
    |--------------------------------------------------------------------------
    | Screenshots
    |--------------------------------------------------------------------------
    |
    | Account proof images. Public storefront view is screenshots only — never
    | the ML ID, server or region.
    |
    */

    'screenshots' => [
        'disk' => env('AUCTION_SCREENSHOT_DISK', 'public'),
        'directory' => env('AUCTION_SCREENSHOT_DIRECTORY', 'auction-screenshots'),
        'max_count' => (int) env('AUCTION_MAX_SCREENSHOTS', 8),
        'max_size_kb' => (int) env('AUCTION_SCREENSHOT_MAX_SIZE_KB', 4096),
    ],
];
