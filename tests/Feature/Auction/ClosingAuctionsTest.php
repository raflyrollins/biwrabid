<?php

use App\Enums\AuctionStatus;
use App\Events\AuctionEnded;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\User;
use Illuminate\Support\Facades\Event;

test('the closer ends an expired auction and records the highest bidder', function () {
    $auction = Auction::factory()->active()->create([
        'starting_price' => 100_000,
        'ends_at' => now()->subMinute(),
    ]);

    $low = User::factory()->create();
    $high = User::factory()->create();

    Bid::factory()->create([
        'auction_id' => $auction->id,
        'bidder_id' => $low->id,
        'amount' => 100_000,
    ]);
    Bid::factory()->create([
        'auction_id' => $auction->id,
        'bidder_id' => $high->id,
        'amount' => 250_000,
    ]);

    $auction->forceFill(['current_price' => 250_000])->save();

    $this->artisan('auctions:close-expired')->assertSuccessful();

    $auction->refresh();

    expect($auction->status)->toBe(AuctionStatus::Ended)
        ->and($auction->winner_id)->toBe($high->id)
        ->and($auction->current_price)->toBe(250_000);
});

test('an expired auction without bids closes with no winner', function () {
    $auction = Auction::factory()->active()->create([
        'ends_at' => now()->subMinute(),
    ]);

    $this->artisan('auctions:close-expired')->assertSuccessful();

    $auction->refresh();

    expect($auction->status)->toBe(AuctionStatus::Ended)
        ->and($auction->winner_id)->toBeNull();
});

test('an auction still inside its window is left alone', function () {
    $auction = Auction::factory()->active()->create([
        'ends_at' => now()->addDay(),
    ]);

    $this->artisan('auctions:close-expired')->assertSuccessful();

    expect($auction->refresh()->status)->toBe(AuctionStatus::Active)
        ->and($auction->winner_id)->toBeNull();
});

test('closing an auction dispatches the ended event', function () {
    Event::fake([AuctionEnded::class]);

    Auction::factory()->active()->create([
        'ends_at' => now()->subMinute(),
    ]);

    $this->artisan('auctions:close-expired')->assertSuccessful();

    Event::assertDispatched(AuctionEnded::class);
});

test('the closer is idempotent', function () {
    Event::fake([AuctionEnded::class]);

    $auction = Auction::factory()->active()->create([
        'ends_at' => now()->subMinute(),
    ]);

    $this->artisan('auctions:close-expired')->assertSuccessful();
    $this->artisan('auctions:close-expired')->assertSuccessful();

    Event::assertDispatchedTimes(AuctionEnded::class, 1);

    expect($auction->refresh()->status)->toBe(AuctionStatus::Ended);
});
