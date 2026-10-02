<?php

use App\Events\BidPlaced;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia;

test('guests are redirected from bidding', function () {
    $auction = Auction::factory()->active()->create();

    $this->post(route('auctions.bids.store', $auction), [
        'amount' => $auction->starting_price,
    ])->assertRedirect(route('login'));
});

test('the opening bid target is the starting price', function () {
    $auction = Auction::factory()->active()->create([
        'starting_price' => 300_000,
    ]);

    expect($auction->minimumNextBid())->toBe(300_000);

    $auction->forceFill(['current_price' => 300_000])->save();

    expect($auction->minimumNextBid())
        ->toBe(300_000 + config('auction.minimum_bid_increment'));
});

test('a bidder can place the opening bid at the starting price', function () {
    $auction = Auction::factory()->active()->create([
        'starting_price' => 500_000,
    ]);
    $bidder = User::factory()->create();

    $response = $this->actingAs($bidder)->post(
        route('auctions.bids.store', $auction),
        ['amount' => 500_000],
    );

    $response->assertRedirect();

    expect($auction->refresh()->current_price)->toBe(500_000)
        ->and($auction->bids()->count())->toBe(1)
        ->and($auction->bids()->firstOrFail()->bidder_id)->toBe($bidder->id);
});

test('a bid amount may arrive as a digits-only string', function () {
    // The number input formats thousands for display but always submits the
    // plain digit string, so the request contract has to accept one.
    $auction = Auction::factory()->active()->create([
        'starting_price' => 500_000,
    ]);
    $bidder = User::factory()->create();

    $this->actingAs($bidder)->post(route('auctions.bids.store', $auction), [
        'amount' => '500000',
    ])->assertRedirect();

    expect($auction->refresh()->current_price)->toBe(500_000)
        ->and($auction->bids()->firstOrFail()->amount)->toBe(500_000);
});

test('a bid amount with thousands separators is rejected', function () {
    $auction = Auction::factory()->active()->create([
        'starting_price' => 500_000,
    ]);
    $bidder = User::factory()->create();

    $this->actingAs($bidder)->post(route('auctions.bids.store', $auction), [
        'amount' => '500.000',
    ])->assertSessionHasErrors('amount');

    expect($auction->refresh()->current_price)->toBeNull();
});

test('placing a bid dispatches a realtime event', function () {
    Event::fake([BidPlaced::class]);

    $auction = Auction::factory()->active()->create([
        'starting_price' => 500_000,
    ]);
    $bidder = User::factory()->create();

    $this->actingAs($bidder)->post(
        route('auctions.bids.store', $auction),
        ['amount' => 500_000],
    );

    Event::assertDispatched(
        BidPlaced::class,
        fn (BidPlaced $event): bool => $event->auction->id === $auction->id,
    );
});

test('a bid below the minimum is rejected', function () {
    $auction = Auction::factory()->active()->create([
        'starting_price' => 500_000,
    ]);
    $bidder = User::factory()->create();

    $this->actingAs($bidder)
        ->post(route('auctions.bids.store', $auction), ['amount' => 499_999])
        ->assertSessionHasErrors('amount');

    expect($auction->refresh()->current_price)->toBeNull()
        ->and($auction->bids()->count())->toBe(0);
});

test('each new bid must include the configured increment', function () {
    $increment = (int) config('auction.minimum_bid_increment');

    $auction = Auction::factory()->active()->create([
        'starting_price' => 500_000,
        'current_price' => 500_000,
    ]);
    $bidder = User::factory()->create();

    $this->actingAs($bidder)
        ->post(route('auctions.bids.store', $auction), [
            'amount' => 500_000 + $increment - 1,
        ])
        ->assertSessionHasErrors('amount');

    $this->actingAs($bidder)
        ->post(route('auctions.bids.store', $auction), [
            'amount' => 500_000 + $increment,
        ])
        ->assertRedirect();

    expect($auction->refresh()->current_price)->toBe(500_000 + $increment);
});

test('a seller cannot bid on their own auction', function () {
    $seller = User::factory()->create();
    $auction = Auction::factory()->for($seller, 'seller')->active()->create();

    $this->actingAs($seller)
        ->post(route('auctions.bids.store', $auction), [
            'amount' => $auction->starting_price,
        ])
        ->assertForbidden();
});

test('bidding is closed on a draft auction', function () {
    $auction = Auction::factory()->create();
    $bidder = User::factory()->create();

    $this->actingAs($bidder)
        ->post(route('auctions.bids.store', $auction), [
            'amount' => $auction->starting_price,
        ])
        ->assertForbidden();
});

test('bidding is closed once the window has passed', function () {
    $auction = Auction::factory()->active()->create([
        'ends_at' => now()->subMinute(),
    ]);
    $bidder = User::factory()->create();

    $this->actingAs($bidder)
        ->post(route('auctions.bids.store', $auction), [
            'amount' => $auction->starting_price,
        ])
        ->assertForbidden();
});

test('the detail page exposes the bid history and window', function () {
    $auction = Auction::factory()->active()->create([
        'starting_price' => 250_000,
    ]);
    $bidder = User::factory()->create();

    Bid::factory()->create([
        'auction_id' => $auction->id,
        'bidder_id' => $bidder->id,
        'amount' => 250_000,
    ]);

    $auction->forceFill(['current_price' => 250_000])->save();

    $response = $this->actingAs($bidder)->get(
        route('auctions.show', $auction),
    );

    $response->assertOk();
    $response->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('auctions/show')
            ->has('bids', 1)
            ->where('bids.0.amount', 250_000)
            ->where('bidding.is_open', true)
            ->where(
                'bidding.minimum_next_bid',
                250_000 + (int) config('auction.minimum_bid_increment'),
            )
            ->where('can.bid', true),
    );
});
