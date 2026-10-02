<?php

declare(strict_types=1);

use App\Actions\StartChat;
use App\Enums\AuctionStatus;
use App\Enums\ChatParticipantRole;
use App\Enums\ChatRoomType;
use App\Events\MessageSent;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\User;
use App\Support\Channels;
use App\Support\ChatPresenter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

/**
 * Pulls the registered `chat.rooms.{…}` channel callback off the broadcaster.
 *
 * @return array{0: callable, 1: string} the callback and its name pattern
 */
function registeredChatChannel(): array
{
    $channels = Broadcast::driver()->getChannels();

    $pattern = $channels
        ->keys()
        ->first(fn (string $name): bool => str_starts_with($name, 'chat.rooms.'));

    expect($pattern)->not->toBeNull();

    return [$channels->get($pattern), $pattern];
}

function endedAuctionFor(User $seller, User $winner): Auction
{
    $auction = Auction::factory()->for($seller, 'seller')->create([
        'status' => AuctionStatus::Ended,
        'winner_id' => $winner->id,
    ]);

    Bid::factory()->for($auction, 'auction')->for($winner, 'bidder')->create([
        'amount' => $auction->starting_price,
    ]);

    return $auction->fresh();
}

it('shows the support room inbox to a signed-in member', function () {
    $member = User::factory()->create();
    $room = ChatRoom::factory()->support($member)->create();

    $this->actingAs($member)
        ->get(route('chat.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('chat/index')
            ->where('isAdminInbox', false)
            ->has('rooms.data', 1)
            ->where('rooms.data.0.uuid', $room->uuid)
        );
});

it('hides one members support room from another', function () {
    $member = User::factory()->create();
    $other = User::factory()->create();

    ChatRoom::factory()->support($member)->create();

    $this->actingAs($other)
        ->get(route('chat.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('rooms.data', 0));
});

it('gives the admin every room except the credential handoff', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create();
    $seller = User::factory()->create();
    $winner = User::factory()->create();
    $auction = endedAuctionFor($seller, $winner);

    ChatRoom::factory()->support($member)->create();
    ChatRoom::factory()->group($auction)->create();
    $credentialRoom = ChatRoom::factory()->auction($auction)->create();

    // The inbox is a moderation queue, not a database dump. A credential room
    // in this list would leak its very contents to the one reader it excludes.
    $this->actingAs($admin)
        ->get(route('chat.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('isAdminInbox', true)
            ->has('rooms.data', 2)
            ->where('rooms.data', fn (Collection $rooms): bool => $rooms
                ->pluck('uuid')
                ->doesntContain($credentialRoom->uuid))
        );
});

it('lets the seller and the winner open the private auction room', function () {
    $seller = User::factory()->create();
    $winner = User::factory()->create();
    $auction = endedAuctionFor($seller, $winner);

    $this->actingAs($seller)
        ->post(route('chat.auction.credentials', $auction))
        ->assertRedirect();

    $this->assertDatabaseHas('chat_rooms', [
        'auction_id' => $auction->id,
        'type' => ChatRoomType::Auction->value,
    ]);
});

it('opens the two auction rooms as separate threads', function () {
    $seller = User::factory()->create();
    $winner = User::factory()->create();
    $auction = endedAuctionFor($seller, $winner);

    $this->actingAs($seller)
        ->post(route('chat.auction.group', $auction))
        ->assertRedirect();

    $this->actingAs($seller)
        ->post(route('chat.auction.credentials', $auction))
        ->assertRedirect();

    // The split is the whole point: one room per kind, not one room where the
    // admin can read the credentials.
    expect(ChatRoom::query()->where('auction_id', $auction->id)->count())->toBe(2);
});

it('reuses one room of each kind across repeated opens', function () {
    $seller = User::factory()->create();
    $winner = User::factory()->create();
    $auction = endedAuctionFor($seller, $winner);

    $startChat = app(StartChat::class);

    $group = $startChat->forAuctionGroup($auction);
    $credentials = $startChat->forAuctionCredentials($auction);

    expect($startChat->forAuctionGroup($auction)?->uuid)->toBe($group?->uuid)
        ->and($startChat->forAuctionCredentials($auction)?->uuid)->toBe($credentials?->uuid)
        ->and($group?->uuid)->not->toBe($credentials?->uuid)
        ->and(ChatRoom::query()->where('auction_id', $auction->id)->count())->toBe(2);
});

it('refuses to open an auction room before there is a winner', function () {
    $seller = User::factory()->create();
    $auction = Auction::factory()->for($seller, 'seller')->active()->create();

    $this->actingAs($seller)
        ->post(route('chat.auction.credentials', $auction))
        ->assertRedirect(route('auctions.show', $auction));

    $this->assertDatabaseCount('chat_rooms', 0);
});

it('keeps a bystander out of an auction room', function () {
    $seller = User::factory()->create();
    $winner = User::factory()->create();
    $stranger = User::factory()->create();
    $auction = endedAuctionFor($seller, $winner);

    // The room does not exist yet, and posting as a stranger must not create it.
    $this->actingAs($stranger)
        ->post(route('chat.auction.credentials', $auction))
        ->assertRedirect(route('auctions.show', $auction));

    expect(ChatRoom::query()->where('auction_id', $auction->id)->exists())->toBeFalse();

    $room = app(StartChat::class)->forAuctionCredentials($auction);

    $this->actingAs($stranger)
        ->get(route('chat.show', $room))
        ->assertForbidden();

    expect($room->canAccess($stranger))->toBeFalse();
});

it('refuses to open an auction room for a live auction with no winner', function () {
    $seller = User::factory()->create();
    $auction = Auction::factory()->for($seller, 'seller')->active()->create();

    $this->actingAs($seller)
        ->post(route('chat.auction.credentials', $auction))
        ->assertRedirect(route('auctions.show', $auction));

    expect(ChatRoom::query()->where('auction_id', $auction->id)->exists())->toBeFalse();
});

it('grants access to the seller, the winner and the admin only', function () {
    $seller = User::factory()->create();
    $winner = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $stranger = User::factory()->create();
    $auction = endedAuctionFor($seller, $winner);

    $room = app(StartChat::class)->forAuctionGroup($auction);

    expect($room->roleFor($seller))->toBe(ChatParticipantRole::Seller)
        ->and($room->roleFor($winner))->toBe(ChatParticipantRole::Winner)
        ->and($room->roleFor($admin))->toBe(ChatParticipantRole::Admin)
        ->and($room->roleFor($stranger))->toBeNull();
});

it('keeps the admin out of the credential room', function () {
    $seller = User::factory()->create();
    $winner = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $stranger = User::factory()->create();
    $auction = endedAuctionFor($seller, $winner);

    $room = app(StartChat::class)->forAuctionCredentials($auction);

    // Everyone else keeps their role; the admin has none here. This one line is
    // the reason the room is separate rather than a flag on one room.
    expect($room->roleFor($seller))->toBe(ChatParticipantRole::Seller)
        ->and($room->roleFor($winner))->toBe(ChatParticipantRole::Winner)
        ->and($room->roleFor($admin))->toBeNull()
        ->and($room->roleFor($stranger))->toBeNull();

    $this->actingAs($admin)
        ->get(route('chat.show', $room))
        ->assertForbidden();
});

it('refuses to let the admin open the credential room', function () {
    $seller = User::factory()->create();
    $winner = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $auction = endedAuctionFor($seller, $winner);

    $this->actingAs($admin)
        ->post(route('chat.auction.credentials', $auction))
        ->assertRedirect(route('auctions.show', $auction));

    $this->assertDatabaseMissing('chat_rooms', [
        'auction_id' => $auction->id,
        'type' => ChatRoomType::Auction->value,
    ]);

    // The group thread is the admin's way in, and it must still work.
    $this->actingAs($admin)
        ->post(route('chat.auction.group', $auction))
        ->assertRedirect();
});

it('lets only participants read a thread', function () {
    $seller = User::factory()->create();
    $winner = User::factory()->create();
    $stranger = User::factory()->create();
    $auction = endedAuctionFor($seller, $winner);

    $room = app(StartChat::class)->forAuctionGroup($auction);
    ChatMessage::factory()->from($seller)->create(['chat_room_id' => $room->id]);

    $this->actingAs($winner)
        ->get(route('chat.show', $room))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('chat/show')
            ->where('room.role', ChatParticipantRole::Winner->value)
            ->has('thread.messages', 1)
            ->where('thread.messages.0.sender_role', ChatParticipantRole::Seller->value)
        );

    $this->actingAs($stranger)
        ->get(route('chat.show', $room))
        ->assertForbidden();
});

it('accepts a message from a participant and refuses a stranger', function () {
    $seller = User::factory()->create();
    $winner = User::factory()->create();
    $stranger = User::factory()->create();
    $auction = endedAuctionFor($seller, $winner);

    $room = app(StartChat::class)->forAuctionGroup($auction);

    $this->actingAs($winner)
        ->post(route('chat.messages.store', $room), ['body' => 'Pembayaran sudah transfer.'])
        ->assertRedirect(route('chat.show', $room));

    $this->assertDatabaseHas('chat_messages', [
        'chat_room_id' => $room->id,
        'user_id' => $winner->id,
        'body' => 'Pembayaran sudah transfer.',
    ]);

    $this->actingAs($stranger)
        ->post(route('chat.messages.store', $room), ['body' => 'Halo'])
        ->assertForbidden();

    expect(ChatMessage::query()->where('body', 'Halo')->count())->toBe(0);
});

it('rejects an empty message', function () {
    $member = User::factory()->create();
    $room = ChatRoom::factory()->support($member)->create();

    $this->actingAs($member)
        ->post(route('chat.messages.store', $room), ['body' => '   '])
        ->assertSessionHasErrors('body');

    $this->assertDatabaseCount('chat_messages', 0);
});

it('rejects a message beyond the configured limit', function () {
    $member = User::factory()->create();
    $room = ChatRoom::factory()->support($member)->create();

    config()->set('chat.message_max_length', 10);

    $this->actingAs($member)
        ->post(route('chat.messages.store', $room), ['body' => str_repeat('a', 11)])
        ->assertSessionHasErrors('body');

    $this->assertDatabaseCount('chat_messages', 0);
});

it('orders the thread oldest first', function () {
    $member = User::factory()->create();
    $room = ChatRoom::factory()->support($member)->create();

    ChatMessage::factory()->from($member)->create([
        'chat_room_id' => $room->id,
        'body' => 'Pertama',
    ]);
    ChatMessage::factory()->from($member)->create([
        'chat_room_id' => $room->id,
        'body' => 'Kedua',
    ]);

    $this->actingAs($member)
        ->get(route('chat.show', $room))
        ->assertInertia(fn ($page) => $page
            ->where('thread.messages.0.body', 'Pertama')
            ->where('thread.messages.1.body', 'Kedua')
        );
});

it('authorises the private broadcast channel for participants only', function () {
    $seller = User::factory()->create();
    $winner = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $stranger = User::factory()->create();
    $auction = endedAuctionFor($seller, $winner);

    $room = app(StartChat::class)->forAuctionCredentials($auction);

    // `Broadcast::routes()` is inert under BROADCAST_CONNECTION=null, so the
    // registered callback is invoked directly — it is the same closure the
    // broadcasting endpoint would run.
    [$callback, $pattern] = registeredChatChannel();

    // The broadcaster authorises `chat.rooms.{roomUuid}`; Channels::chatRoom()
    // must build a name that matches it exactly, placeholder included, or the
    // socket would subscribe to a channel the server never authorises.
    expect(Str::replace('{roomUuid}', $room->uuid, $pattern))
        ->toBe(Channels::chatRoom($room));

    expect($callback($seller, $room->uuid))->toBeTrue()
        ->and($callback($winner, $room->uuid))->toBeTrue()
        ->and($callback($stranger, $room->uuid))->toBeFalse()
        // An admin is a participant in the group thread but not in this one, so
        // a live subscription must fail here too — otherwise the realtime path
        // would leak what the HTTP path refuses.
        ->and($callback($admin, $room->uuid))->toBeFalse();
});

it('authorises the admin into the group broadcast channel', function () {
    $seller = User::factory()->create();
    $winner = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $auction = endedAuctionFor($seller, $winner);

    $room = app(StartChat::class)->forAuctionGroup($auction);

    [$callback] = registeredChatChannel();

    expect($callback($admin, $room->uuid))->toBeTrue();
});

it('never authorises a room that does not exist', function () {
    [$callback] = registeredChatChannel();

    expect($callback(User::factory()->create(), '00000000-0000-4000-8000-000000000000'))
        ->toBeFalse();
});

it('bans guests from every chat route', function () {
    $this->get(route('chat.index'))->assertRedirect(route('login'));
    $this->post(route('chat.support'))->assertRedirect(route('login'));
});

it('keeps the room uuid out of the message payload contract', function () {
    $member = User::factory()->create();
    $room = ChatRoom::factory()->support($member)->create();

    $message = ChatMessage::factory()->from($member)->create([
        'chat_room_id' => $room->id,
    ]);

    $presented = ChatPresenter::message($message);

    expect($presented)->toHaveKeys(['id', 'body', 'sender_name', 'sender_role', 'created_at'])
        ->and($presented)->not->toHaveKey('chat_room_id')
        ->and($presented)->not->toHaveKey('user_id');
});

it('exposes both chat buttons to the seller and the winner', function () {
    $seller = User::factory()->create();
    $winner = User::factory()->create();
    $stranger = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $auction = endedAuctionFor($seller, $winner);

    $this->actingAs($seller)
        ->get(route('auctions.show', $auction))
        ->assertInertia(fn ($page) => $page
            ->where('can.chat_group', true)
            ->where('can.chat_credentials', true));

    $this->actingAs($winner)
        ->get(route('auctions.show', $auction))
        ->assertInertia(fn ($page) => $page
            ->where('can.chat_group', true)
            ->where('can.chat_credentials', true));

    // The admin moderates the group thread and has no business in the credential
    // handoff, so only one of the two buttons is theirs.
    $this->actingAs($admin)
        ->get(route('auctions.show', $auction))
        ->assertInertia(fn ($page) => $page
            ->where('can.chat_group', true)
            ->where('can.chat_credentials', false));

    $this->actingAs($stranger)
        ->get(route('auctions.show', $auction))
        ->assertInertia(fn ($page) => $page
            ->where('can.chat_group', false)
            ->where('can.chat_credentials', false));

    $this->get(route('auctions.show', $auction))
        ->assertInertia(fn ($page) => $page
            ->where('can.chat_group', false)
            ->where('can.chat_credentials', false));
});

it('tells each storefront button which counterpart its thread has', function () {
    $seller = User::factory()->create();
    $winner = User::factory()->create();
    $stranger = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $auction = endedAuctionFor($seller, $winner);

    // Each thread has to name the other side, so a seller is never pointed at
    // themselves. The roles are per thread now, not one shared value.
    $this->actingAs($seller)
        ->get(route('auctions.show', $auction))
        ->assertInertia(fn ($page) => $page
            ->where('can.chat_group_role', 'seller')
            ->where('can.chat_credentials_role', 'seller'));

    $this->actingAs($winner)
        ->get(route('auctions.show', $auction))
        ->assertInertia(fn ($page) => $page
            ->where('can.chat_group_role', 'winner')
            ->where('can.chat_credentials_role', 'winner'));

    $this->actingAs($admin)
        ->get(route('auctions.show', $auction))
        ->assertInertia(fn ($page) => $page
            ->where('can.chat_group_role', 'admin')
            ->where('can.chat_credentials_role', null));

    $this->actingAs($stranger)
        ->get(route('auctions.show', $auction))
        ->assertInertia(fn ($page) => $page
            ->where('can.chat_group_role', null)
            ->where('can.chat_credentials_role', null));
});

it('has no chat role to label while the auction has no winner', function () {
    $seller = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $auction = Auction::factory()->for($seller, 'seller')->active()->create();

    // Admins can join any *existing* group room, but there is nothing to join
    // yet, and a credential room must never exist before a winner.
    $this->actingAs($seller)
        ->get(route('auctions.show', $auction))
        ->assertInertia(fn ($page) => $page
            ->where('can.chat_group_role', null)
            ->where('can.chat_credentials_role', null));

    $this->actingAs($admin)
        ->get(route('auctions.show', $auction))
        ->assertInertia(fn ($page) => $page
            ->where('can.chat_group_role', null)
            ->where('can.chat_credentials_role', null));
});

it('hides both chat buttons while the auction has no winner', function () {
    $seller = User::factory()->create();
    $auction = Auction::factory()->for($seller, 'seller')->active()->create();

    $this->actingAs($seller)
        ->get(route('auctions.show', $auction))
        ->assertInertia(fn ($page) => $page
            ->where('can.chat_group', false)
            ->where('can.chat_credentials', false));
});

it('tells the thread header whether an admin can see the room', function () {
    $seller = User::factory()->create();
    $winner = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $auction = endedAuctionFor($seller, $winner);

    $startChat = app(StartChat::class);

    $this->actingAs($winner)
        ->get(route('chat.show', $startChat->forAuctionGroup($auction)))
        ->assertInertia(fn ($page) => $page->where('room.admits_admin', true));

    // The credential room has to say so, rather than claiming a moderator who
    // cannot read it.
    $this->actingAs($winner)
        ->get(route('chat.show', $startChat->forAuctionCredentials($auction)))
        ->assertInertia(fn ($page) => $page->where('room.admits_admin', false));
});

it('broadcasts every message on the room private channel', function () {
    Event::fake([MessageSent::class]);

    $member = User::factory()->create();
    $room = ChatRoom::factory()->support($member)->create();

    $this->actingAs($member)
        ->post(route('chat.messages.store', $room), ['body' => 'Halo admin']);

    Event::assertDispatched(MessageSent::class, function (MessageSent $event) use ($room): bool {
        return $event->message->body === 'Halo admin'
            && $event->broadcastOn()[0]->name === 'private-chat.rooms.'.$room->uuid
            && $event->broadcastAs() === 'chat.message.sent'
            && $event->broadcastWith()['room_uuid'] === $room->uuid
            && $event->broadcastWith()['message']['sender_role'] === 'member';
    });
});
