<?php

declare(strict_types=1);

use App\Models\Auction;
use App\Models\User;
use App\Support\AuctionConfig;
use App\Support\AuctionPresenter;

/**
 * ICU separates the currency symbol from the amount with a non-breaking space,
 * so it is normalised away before comparing.
 */
function plain(string $formatted): string
{
    return str_replace("\u{00A0}", ' ', $formatted);
}

test('prices are formatted as whole rupiah', function (int $value, string $expected) {
    expect(plain(AuctionConfig::price($value)))->toBe($expected);
})->with([
    [1_500_000, 'Rp 1.500.000'],
    [1_000_000, 'Rp 1.000.000'],
    [1_000, 'Rp 1.000'],
    [250_000, 'Rp 250.000'],
    [10_000_000, 'Rp 10.000.000'],
    [0, 'Rp 0'],
]);

/**
 * Indonesian uses "," for decimals and "." for thousands, so a whole-rupiah
 * amount is any format with no comma at all. The trailing ",00" that used to be
 * rendered is exactly what this rejects.
 */
test('formatted prices carry no decimal separator', function (int $value) {
    $formatted = AuctionConfig::price($value);

    expect($formatted)->not->toContain(',')
        ->and($formatted)->not->toContain('00,00');
})->with([1_000, 1_500_000, 250_000, 999_999_999]);

test('the price label on a listing page has no decimals', function () {
    $seller = User::factory()->create();
    $auction = Auction::factory()->for($seller, 'seller')->create([
        'starting_price' => 1_500_000,
    ]);

    $summary = AuctionPresenter::summary($auction->refresh());

    expect(plain($summary['starting_price_label']))->toBe('Rp 1.500.000')
        ->and(plain($summary['price']))->toBe('Rp 1.500.000');
});

test('the highest bid label has no decimals', function () {
    $seller = User::factory()->create();
    $bidder = User::factory()->create();
    $auction = Auction::factory()->for($seller, 'seller')->active()->create([
        'current_price' => 2_750_000,
    ]);

    $auction->bids()->create(['bidder_id' => $bidder->id, 'amount' => 2_750_000]);

    $summary = AuctionPresenter::summary($auction->refresh());

    expect(plain($summary['current_price_label']))->toBe('Rp 2.750.000');
});
