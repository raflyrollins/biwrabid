<?php

declare(strict_types=1);

use App\Models\Auction;
use App\Models\User;

/*
 * The flash props are what the result modal renders, so a key that never
 * reaches the client is a silent failure: the action happens, the page changes,
 * and the user is told nothing. `error` in particular used to be dropped by
 * `HandleInertiaRequests`, which left every early-close rejection silent.
 */

test('a success flash reaches the page props', function () {
    $seller = User::factory()->create();
    $auction = Auction::factory()->for($seller, 'seller')->active()->create();

    $this->actingAs($seller)
        ->followingRedirects()
        ->post(route('auctions.end-early', $auction))
        ->assertInertia(fn ($page) => $page
            ->has('flash.status')
            ->where('flash.error', null));
});

test('an error flash reaches the page props', function () {
    $seller = User::factory()->create();
    $auction = Auction::factory()->for($seller, 'seller')->active()->create([
        'reserve_price' => 5_000_000,
    ]);

    $bidder = User::factory()->create();
    $auction->bids()->create([
        'bidder_id' => $bidder->id,
        'amount' => 2_000_000,
    ]);

    $this->actingAs($seller)
        ->followingRedirects()
        ->post(route('auctions.end-early', $auction))
        ->assertInertia(fn ($page) => $page
            ->where('flash.error', __('ui.auctions.early_close.errors.below_reserve'))
            ->where('flash.status', null));
});
