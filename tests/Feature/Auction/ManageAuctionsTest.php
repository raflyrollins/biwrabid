<?php

use App\Enums\AuctionStatus;
use App\Models\Auction;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

test('guests are redirected from the seller area', function () {
    $this->get(route('my-auctions.index'))->assertRedirect(route('login'));
    $this->get(route('auctions.create'))->assertRedirect(route('login'));
});

test('the seller area lists only the signed-in seller auctions', function () {
    $seller = User::factory()->create();
    $other = User::factory()->create();

    Auction::factory()->for($seller, 'seller')->count(2)->create();
    Auction::factory()->for($other, 'seller')->create();

    $response = $this->actingAs($seller)->get(route('my-auctions.index'));

    $response->assertOk();
    $response->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('auctions/my')
            ->has('auctions.data', 2)
            ->where(
                'minimumDurationHours',
                config('auction.minimum_duration_hours'),
            ),
    );
});

test('a seller can create a draft auction with screenshots', function () {
    Storage::fake('public');

    $seller = User::factory()->create();

    $response = $this->actingAs($seller)->post(route('auctions.store'), [
        'title' => 'Akun Mythic 500 skin',
        'description' => 'Akun lengkap dengan koleksi skin.',
        'starting_price' => config('auction.minimum_starting_price'),
        'screenshots' => [UploadedFile::fake()->image('screen-1.png')],
    ]);

    $response->assertRedirect(route('my-auctions.index'));

    $auction = Auction::query()->firstOrFail();

    expect($auction->seller_id)->toBe($seller->id)
        ->and($auction->status)->toBe(AuctionStatus::Draft)
        ->and($auction->screenshots)->toHaveCount(1);

    Storage::disk('public')->assertExists($auction->screenshots->first()->path);
});

test('the starting price may arrive as a digits-only string', function () {
    // The number input groups thousands for display but always submits the
    // plain digit string, so the request contract has to accept one.
    Storage::fake('public');

    $seller = User::factory()->create();

    $this->actingAs($seller)->post(route('auctions.store'), [
        'title' => 'Akun Mythic 500 skin',
        'description' => 'Akun lengkap dengan koleksi skin.',
        'starting_price' => '1500000',
        'screenshots' => [UploadedFile::fake()->image('screen-1.png')],
    ])->assertRedirect(route('my-auctions.index'));

    expect(Auction::query()->firstOrFail()->starting_price)->toBe(1_500_000);
});

test('a starting price with thousands separators is rejected', function () {
    Storage::fake('public');

    $seller = User::factory()->create();

    $this->actingAs($seller)->post(route('auctions.store'), [
        'title' => 'Akun Mythic 500 skin',
        'description' => 'Akun lengkap dengan koleksi skin.',
        'starting_price' => '1.500.000',
        'screenshots' => [UploadedFile::fake()->image('screen-1.png')],
    ])->assertSessionHasErrors('starting_price');

    expect(Auction::query()->count())->toBe(0);
});

test('creating an auction requires a screenshot', function () {
    $seller = User::factory()->create();

    $response = $this->actingAs($seller)->post(route('auctions.store'), [
        'title' => 'Akun Mythic',
        'description' => 'Deskripsi.',
        'starting_price' => config('auction.minimum_starting_price'),
    ]);

    $response->assertSessionHasErrors('screenshots');
    expect(Auction::query()->count())->toBe(0);
});

test('the starting price must meet the configured minimum', function () {
    $seller = User::factory()->create();

    $response = $this->actingAs($seller)->post(route('auctions.store'), [
        'title' => 'Akun Mythic',
        'description' => 'Deskripsi.',
        'starting_price' => config('auction.minimum_starting_price') - 1,
        'screenshots' => [UploadedFile::fake()->image('screen-1.png')],
    ]);

    $response->assertSessionHasErrors('starting_price');
});

test('the owner can update their draft auction', function () {
    $seller = User::factory()->create();
    $auction = Auction::factory()->for($seller, 'seller')->create();

    $response = $this->actingAs($seller)->put(route('auctions.update', $auction), [
        'title' => 'Judul baru',
        'description' => 'Deskripsi baru.',
        'starting_price' => config('auction.minimum_starting_price'),
    ]);

    $response->assertRedirect(route('my-auctions.index'));
    $this->assertDatabaseHas('auctions', [
        'id' => $auction->id,
        'title' => 'Judul baru',
    ]);
});

test('a seller cannot update another sellers auction', function () {
    $seller = User::factory()->create();
    $other = User::factory()->create();
    $auction = Auction::factory()->for($other, 'seller')->create();

    $this->actingAs($seller)
        ->put(route('auctions.update', $auction), [
            'title' => 'Judul baru',
            'description' => 'Deskripsi baru.',
            'starting_price' => config('auction.minimum_starting_price'),
        ])
        ->assertForbidden();
});

test('a seller can publish a draft with a valid end time', function () {
    $seller = User::factory()->create();
    $auction = Auction::factory()->for($seller, 'seller')->create();
    $endsAt = now()->addHours(config('auction.minimum_duration_hours'))->addDay();

    $response = $this->actingAs($seller)->post(
        route('auctions.publish', $auction),
        ['ends_at' => $endsAt->toISOString()],
    );

    $response->assertRedirect(route('auctions.show', $auction));

    $auction->refresh();

    expect($auction->status)->toBe(AuctionStatus::Active)
        ->and($auction->starts_at)->not->toBeNull()
        ->and($auction->ends_at->timestamp)->toBe($endsAt->timestamp);
});

test('an already published auction cannot be published again', function () {
    $seller = User::factory()->create();
    $auction = Auction::factory()->for($seller, 'seller')->active()->create();
    $endsAt = $auction->ends_at;

    $this->actingAs($seller)
        ->post(route('auctions.publish', $auction), [
            'ends_at' => now()->addDays(7)->toISOString(),
        ])
        ->assertForbidden();

    $auction->refresh();

    // The refused republish must not touch the original schedule either.
    expect($auction->ends_at->timestamp)->toBe($endsAt->timestamp);
});

test('publishing must respect the configured duration bounds', function () {
    $seller = User::factory()->create();
    $auction = Auction::factory()->for($seller, 'seller')->create();

    $this->actingAs($seller)
        ->post(route('auctions.publish', $auction), [
            'ends_at' => now()
                ->addHours(config('auction.minimum_duration_hours'))
                ->subMinute()
                ->toISOString(),
        ])
        ->assertSessionHasErrors('ends_at');

    $this->actingAs($seller)
        ->post(route('auctions.publish', $auction), [
            'ends_at' => now()
                ->addHours(config('auction.maximum_duration_hours'))
                ->addMinute()
                ->toISOString(),
        ])
        ->assertSessionHasErrors('ends_at');

    $this->actingAs($seller)
        ->post(route('auctions.publish', $auction), ['ends_at' => 'not-a-date'])
        ->assertSessionHasErrors('ends_at');

    expect($auction->refresh()->status)->toBe(AuctionStatus::Draft);
});

test('a seller can cancel an active auction', function () {
    $seller = User::factory()->create();
    $auction = Auction::factory()->for($seller, 'seller')->active()->create();

    $response = $this->actingAs($seller)->post(route('auctions.cancel', $auction));

    $response->assertRedirect(route('my-auctions.index'));
    expect($auction->refresh()->status)->toBe(AuctionStatus::Cancelled);
});

test('a draft auction cannot be cancelled', function () {
    $seller = User::factory()->create();
    $auction = Auction::factory()->for($seller, 'seller')->create();

    $this->actingAs($seller)
        ->post(route('auctions.cancel', $auction))
        ->assertForbidden();
});

test('a seller can delete their draft auction', function () {
    $seller = User::factory()->create();
    $auction = Auction::factory()->for($seller, 'seller')->create();

    $response = $this->actingAs($seller)->delete(
        route('auctions.destroy', $auction),
    );

    $response->assertRedirect(route('my-auctions.index'));
    $this->assertDatabaseMissing('auctions', ['id' => $auction->id]);
});

test('an active auction cannot be deleted', function () {
    $seller = User::factory()->create();
    $auction = Auction::factory()->for($seller, 'seller')->active()->create();

    $this->actingAs($seller)
        ->delete(route('auctions.destroy', $auction))
        ->assertForbidden();
});
