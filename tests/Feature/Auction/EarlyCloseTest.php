<?php

declare(strict_types=1);

use App\Actions\CloseAuction;
use App\Enums\AuctionStatus;
use App\Enums\CloseReason;
use App\Events\AuctionEnded;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Event::fake([AuctionEnded::class]);
});

test('a seller may close an active auction early when nobody has bid', function () {
    $seller = User::factory()->create();
    $auction = Auction::factory()->for($seller, 'seller')->active()->create();

    $this->actingAs($seller)
        ->post(route('auctions.end-early', $auction))
        ->assertRedirect(route('my-auctions.index'))
        ->assertSessionHas('status');

    $auction->refresh();

    expect($auction->status)->toBe(AuctionStatus::Ended)
        ->and($auction->winner_id)->toBeNull()
        ->and($auction->ended_reason)->toBe(CloseReason::SellerEnded)
        ->and($auction->ended_at)->not->toBeNull();
});

test('the highest bidder wins when a seller closes early', function () {
    $seller = User::factory()->create();
    $low = User::factory()->create();
    $high = User::factory()->create();
    $auction = Auction::factory()->for($seller, 'seller')->active()->create();

    Bid::factory()->for($auction, 'auction')->for($low, 'bidder')->create(['amount' => 100_000]);
    Bid::factory()->for($auction, 'auction')->for($high, 'bidder')->create(['amount' => 250_000]);

    $this->actingAs($seller)->post(route('auctions.end-early', $auction));

    expect($auction->refresh())
        ->status->toBe(AuctionStatus::Ended)
        ->winner_id->toBe($high->id)
        ->ended_reason->toBe(CloseReason::SellerEnded);
});

test('an equal highest bid is won by the earliest bid', function () {
    $seller = User::factory()->create();
    $first = User::factory()->create();
    $second = User::factory()->create();
    $auction = Auction::factory()->for($seller, 'seller')->active()->create();

    Bid::factory()->for($auction, 'auction')->for($first, 'bidder')->create(['amount' => 300_000]);
    Bid::factory()->for($auction, 'auction')->for($second, 'bidder')->create(['amount' => 300_000]);

    $this->actingAs($seller)->post(route('auctions.end-early', $auction));

    expect($auction->refresh()->winner_id)->toBe($first->id);
});

test('a seller cannot close early below their own reserve price', function () {
    $seller = User::factory()->create();
    $bidder = User::factory()->create();
    $auction = Auction::factory()->for($seller, 'seller')->active()->create([
        'reserve_price' => 500_000,
    ]);

    Bid::factory()->for($auction, 'auction')->for($bidder, 'bidder')->create(['amount' => 250_000]);

    $this->actingAs($seller)
        ->post(route('auctions.end-early', $auction))
        ->assertRedirect(route('my-auctions.index'))
        ->assertSessionHas('error');

    expect($auction->refresh()->status)->toBe(AuctionStatus::Active)
        ->and($auction->winner_id)->toBeNull();
});

test('the reserve price is met exactly, not exceeded', function () {
    $seller = User::factory()->create();
    $bidder = User::factory()->create();
    $auction = Auction::factory()->for($seller, 'seller')->active()->create([
        'reserve_price' => 250_000,
    ]);

    Bid::factory()->for($auction, 'auction')->for($bidder, 'bidder')->create(['amount' => 250_000]);

    expect($auction->earlyCloseBlockedReason())->toBeNull();

    $this->actingAs($seller)->post(route('auctions.end-early', $auction));

    expect($auction->refresh()->winner_id)->toBe($bidder->id);
});

test('a reserve price blocks an early close even with no bids', function () {
    $seller = User::factory()->create();
    $auction = Auction::factory()->for($seller, 'seller')->active()->create([
        'reserve_price' => 500_000,
    ]);

    $this->actingAs($seller)
        ->post(route('auctions.end-early', $auction))
        ->assertSessionHas('error');

    expect($auction->refresh()->status)->toBe(AuctionStatus::Active);
});

test('the anti-snipe window blocks a close right after a bid', function () {
    config(['auction.minimum_minutes_since_last_bid' => 30]);

    $seller = User::factory()->create();
    $bidder = User::factory()->create();
    $auction = Auction::factory()->for($seller, 'seller')->active()->create();

    Bid::factory()->for($auction, 'auction')->for($bidder, 'bidder')->create([
        'amount' => 250_000,
        'created_at' => now()->subMinutes(2),
        'updated_at' => now()->subMinutes(2),
    ]);

    expect($auction->earlyCloseBlockedReason())
        ->toBe('auctions.early_close.errors.too_recent_bid');

    $this->actingAs($seller)
        ->post(route('auctions.end-early', $auction))
        ->assertSessionHas('error');

    expect($auction->refresh()->status)->toBe(AuctionStatus::Active);
});

test('the anti-snipe window is disabled when it is configured to zero', function () {
    config(['auction.minimum_minutes_since_last_bid' => 0]);

    $seller = User::factory()->create();
    $bidder = User::factory()->create();
    $auction = Auction::factory()->for($seller, 'seller')->active()->create();

    Bid::factory()->for($auction, 'auction')->for($bidder, 'bidder')->create([
        'amount' => 250_000,
        'created_at' => now()->subSecond(),
        'updated_at' => now()->subSecond(),
    ]);

    expect($auction->earlyCloseBlockedReason())->toBeNull();

    $this->actingAs($seller)->post(route('auctions.end-early', $auction));

    expect($auction->refresh()->winner_id)->toBe($bidder->id);
});

test('a bid older than the anti-snipe window no longer blocks the close', function () {
    config(['auction.minimum_minutes_since_last_bid' => 30]);

    $seller = User::factory()->create();
    $bidder = User::factory()->create();
    $auction = Auction::factory()->for($seller, 'seller')->active()->create();

    Bid::factory()->for($auction, 'auction')->for($bidder, 'bidder')->create([
        'amount' => 250_000,
        'created_at' => now()->subMinutes(31),
        'updated_at' => now()->subMinutes(31),
    ]);

    expect($auction->earlyCloseBlockedReason())->toBeNull();
});

test('only the seller may close an auction early', function () {
    $seller = User::factory()->create();
    $stranger = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $auction = Auction::factory()->for($seller, 'seller')->active()->create();

    $this->actingAs($stranger)
        ->post(route('auctions.end-early', $auction))
        ->assertForbidden();

    $this->actingAs($admin)
        ->post(route('auctions.end-early', $auction))
        ->assertForbidden();

    expect($auction->refresh()->status)->toBe(AuctionStatus::Active);
});

test('a draft or already closed auction cannot be closed early', function () {
    $seller = User::factory()->create();
    $draft = Auction::factory()->for($seller, 'seller')->create();
    $ended = Auction::factory()->for($seller, 'seller')->ended()->create();

    $this->actingAs($seller)->post(route('auctions.end-early', $draft))->assertForbidden();
    $this->actingAs($seller)->post(route('auctions.end-early', $ended))->assertForbidden();
});

test('guests cannot close an auction early', function () {
    $auction = Auction::factory()->active()->create();

    $this->post(route('auctions.end-early', $auction))->assertRedirect(route('login'));
});

test('the broadcast reports the seller as the reason for closing', function () {
    $seller = User::factory()->create();
    $bidder = User::factory()->create();
    $auction = Auction::factory()->for($seller, 'seller')->active()->create();

    Bid::factory()->for($auction, 'auction')->for($bidder, 'bidder')->create(['amount' => 250_000]);

    $this->actingAs($seller)->post(route('auctions.end-early', $auction));

    Event::assertDispatched(
        AuctionEnded::class,
        fn (AuctionEnded $event): bool => $event->reason === CloseReason::SellerEnded
            && $event->broadcastWith()['ended_reason'] === 'seller_ended'
            && $event->broadcastWith()['winner_name'] === $bidder->name,
    );
});

test('the scheduler path still records time_expired', function () {
    $seller = User::factory()->create();
    $bidder = User::factory()->create();
    $auction = Auction::factory()->for($seller, 'seller')->active()->create();

    Bid::factory()->for($auction, 'auction')->for($bidder, 'bidder')->create(['amount' => 250_000]);

    $auction->forceFill(['ends_at' => now()->subMinute()])->save();

    $this->artisan('auctions:close-expired')->assertSuccessful();

    expect($auction->refresh())
        ->status->toBe(AuctionStatus::Ended)
        ->winner_id->toBe($bidder->id)
        ->ended_reason->toBe(CloseReason::TimeExpired);

    Event::assertDispatched(
        AuctionEnded::class,
        fn (AuctionEnded $event): bool => $event->reason === CloseReason::TimeExpired,
    );
});

test('closing early is idempotent', function () {
    $seller = User::factory()->create();
    $auction = Auction::factory()->for($seller, 'seller')->active()->create();

    $close = app(CloseAuction::class);

    expect($close($auction, CloseReason::SellerEnded))->not->toBeNull()
        ->and($close($auction, CloseReason::SellerEnded))->toBeNull();
});

test('the seller list tells the seller who the auction would go to', function () {
    $seller = User::factory()->create();
    $bidder = User::factory()->create();
    $auction = Auction::factory()->for($seller, 'seller')->active()->create();

    Bid::factory()->for($auction, 'auction')->for($bidder, 'bidder')->create(['amount' => 250_000]);

    $this->actingAs($seller)
        ->get(route('my-auctions.index'))
        ->assertInertia(fn ($page) => $page
            ->where('auctions.data.0.can_end_early', true)
            ->where('auctions.data.0.end_early_blocked_reason', null)
            ->where('auctions.data.0.highest_bidder_name', $bidder->name)
        );
});

test('the seller list surfaces the reason an early close is blocked', function () {
    $seller = User::factory()->create();
    $bidder = User::factory()->create();
    $auction = Auction::factory()->for($seller, 'seller')->active()->create([
        'reserve_price' => 500_000,
    ]);

    Bid::factory()->for($auction, 'auction')->for($bidder, 'bidder')->create(['amount' => 250_000]);

    $this->actingAs($seller)
        ->get(route('my-auctions.index'))
        ->assertInertia(fn ($page) => $page
            ->where('auctions.data.0.can_end_early', false)
            ->where(
                'auctions.data.0.end_early_blocked_reason',
                'Tawaran tertinggi masih di bawah harga pengamanmu.',
            )
        );
});

test('the reserve price is stored and validated', function () {
    $seller = User::factory()->create();

    Storage::fake('public');

    $this->actingAs($seller)
        ->post(route('auctions.store'), [
            'title' => 'Akun game',
            'description' => 'Deskripsi',
            'starting_price' => '1000000',
            'reserve_price' => '2500000',
            'screenshots' => [UploadedFile::fake()->image('a.jpg')],
        ])
        ->assertRedirect(route('my-auctions.index'));

    expect(Auction::query()->latest('id')->first()->reserve_price)->toBe(2_500_000);

    $this->actingAs($seller)
        ->from(route('auctions.create'))
        ->post(route('auctions.store'), [
            'title' => 'Akun game',
            'description' => 'Deskripsi',
            'starting_price' => '1000000',
            'reserve_price' => '500000',
            'screenshots' => [UploadedFile::fake()->image('a.jpg')],
        ])
        ->assertSessionHasErrors('reserve_price');

    $this->actingAs($seller)
        ->from(route('auctions.create'))
        ->post(route('auctions.store'), [
            'title' => 'Akun game',
            'description' => 'Deskripsi',
            'starting_price' => '1000000',
            'reserve_price' => '1500',
            'screenshots' => [UploadedFile::fake()->image('a.jpg')],
        ])
        ->assertSessionHasErrors('reserve_price');
});

test('the reserve price stays optional', function () {
    $seller = User::factory()->create();

    Storage::fake('public');

    $this->actingAs($seller)
        ->post(route('auctions.store'), [
            'title' => 'Akun game',
            'description' => 'Deskripsi',
            'starting_price' => '1000000',
            'reserve_price' => '',
            'screenshots' => [UploadedFile::fake()->image('a.jpg')],
        ])
        ->assertRedirect(route('my-auctions.index'));

    expect(Auction::query()->latest('id')->first()->reserve_price)->toBeNull();
});

test('a seller may edit the reserve price on a draft', function () {
    $seller = User::factory()->create();
    $auction = Auction::factory()->for($seller, 'seller')->create([
        'reserve_price' => 1_000_000,
    ]);

    $this->actingAs($seller)
        ->put(route('auctions.update', $auction), [
            'title' => $auction->title,
            'description' => $auction->description,
            'starting_price' => '1000000',
            'reserve_price' => '4000000',
        ])
        ->assertRedirect(route('my-auctions.index'));

    expect($auction->refresh()->reserve_price)->toBe(4_000_000);
});
