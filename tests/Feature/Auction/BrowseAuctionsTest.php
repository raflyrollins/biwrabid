<?php

use App\Models\Auction;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('the storefront lists only active auctions', function () {
    $active = Auction::factory()->active()->create();
    Auction::factory()->create();
    Auction::factory()->ended()->create();
    Auction::factory()->cancelled()->create();

    $response = $this->get(route('auctions.index'));

    $response->assertOk();
    $response->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('auctions/index')
            ->has('auctions.data', 1)
            ->where('auctions.data.0.uuid', $active->uuid)
            ->where('filters.q', null),
    );
});

test('the storefront can be full-text searched', function () {
    $match = Auction::factory()->active()->create([
        'title' => 'Akun Mythic Glory',
    ]);
    Auction::factory()->active()->create([
        'title' => 'Akun Legend Biasa',
    ]);

    $response = $this->get(route('auctions.index', ['q' => 'Mythic']));

    $response->assertOk();
    $response->assertInertia(
        fn (AssertableInertia $page) => $page
            ->has('auctions.data', 1)
            ->where('auctions.data.0.uuid', $match->uuid)
            ->where('filters.q', 'Mythic'),
    );
});

test('guests cannot view a draft auction', function () {
    $auction = Auction::factory()->create();

    $this->get(route('auctions.show', $auction))->assertForbidden();
});

test('the seller can view their own draft auction', function () {
    $seller = User::factory()->create();
    $auction = Auction::factory()->for($seller, 'seller')->create();

    $response = $this->actingAs($seller)->get(route('auctions.show', $auction));

    $response->assertOk();
    $response->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('auctions/show')
            ->where('auction.uuid', $auction->uuid)
            ->where('can.update', true)
            ->where('can.cancel', false),
    );
});

test('guests can view an active auction', function () {
    $auction = Auction::factory()->active()->create();

    $response = $this->get(route('auctions.show', $auction));

    $response->assertOk();
    $response->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('auctions/show')
            ->where('auction.uuid', $auction->uuid)
            ->where('can.update', false)
            ->where('can.cancel', false),
    );
});
