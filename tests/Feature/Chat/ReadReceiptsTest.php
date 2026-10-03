<?php

declare(strict_types=1);

use App\Events\MessagesRead;
use App\Models\Auction;
use App\Models\ChatMessage;
use App\Models\ChatMessageRead;
use App\Models\ChatRoom;
use App\Models\User;
use App\Support\Channels;
use App\Support\ChatPresenter;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * Read receipts, and the double tick they drive.
 *
 * The rule under test throughout: a message shows a double tick once every
 * participant *except its sender* has read it. That is why nearly every case here
 * uses a support room - one member, one admin, two people - because a group room
 * would also need an admin to read, which is correct but makes arranging the
 * assertion much harder.
 *
 * The room list and the thread both render through the presenter, so the tick is
 * asserted where the client actually reads it rather than on the action that
 * writes it.
 */
it('reports a message as read once the other participant has read it', function (): void {
    $member = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $room = ChatRoom::factory()->support($member)->create();

    $message = ChatMessage::factory()->from($member)->create([
        'chat_room_id' => $room->id,
    ]);

    expect(ChatPresenter::message($message->fresh())['read_by_all'])->toBeFalse();

    $this->actingAs($admin)
        ->post(route('chat.messages.read', $room))
        ->assertRedirect(route('chat.index', ['c' => $room->uuid]));

    expect(ChatPresenter::message($message->fresh())['read_by_all'])->toBeTrue();
});

it('waits for every reader, not just the first', function (): void {
    $member = User::factory()->create();
    $first = User::factory()->admin()->create();
    $second = User::factory()->admin()->create();
    $room = ChatRoom::factory()->support($member)->create();

    $message = ChatMessage::factory()->from($member)->create([
        'chat_room_id' => $room->id,
    ]);

    $this->actingAs($first)->post(route('chat.messages.read', $room));

    // One of two admins is not "everyone": the second one still has not looked.
    expect(ChatPresenter::message($message->fresh())['read_by_all'])->toBeFalse();

    $this->actingAs($second)->post(route('chat.messages.read', $room));

    expect(ChatPresenter::message($message->fresh())['read_by_all'])->toBeTrue();
});

it('never counts the sender as a reader', function (): void {
    $member = User::factory()->create();
    User::factory()->admin()->create();
    $room = ChatRoom::factory()->support($member)->create();

    $message = ChatMessage::factory()->from($member)->create([
        'chat_room_id' => $room->id,
    ]);

    // The sender reading their own message must not complete their own double
    // tick; only the admin's receipt can.
    $this->actingAs($member)->post(route('chat.messages.read', $room));

    expect(ChatPresenter::message($message->fresh())['read_by_all'])->toBeFalse()
        ->and(ChatMessageRead::query()->where('chat_message_id', $message->id)->count())->toBe(0);
});

it('leaves the credential handoff waiting on the winner alone', function (): void {
    $seller = User::factory()->create();
    $winner = User::factory()->create();
    $admin = User::factory()->admin()->create();

    $auction = Auction::factory()->for($seller, 'seller')->create(['winner_id' => $winner->id]);
    $room = ChatRoom::factory()->auction($auction)->create();

    $message = ChatMessage::factory()->from($seller)->create(['chat_room_id' => $room->id]);

    // The admin is not a participant here, so their reading is not part of the
    // question at all - `ChatRoomType::admitsAdmin()` has to reach the receipt
    // logic and not only the page.
    $this->actingAs($admin)
        ->post(route('chat.messages.read', $room))
        ->assertForbidden();

    $this->actingAs($winner)->post(route('chat.messages.read', $room));

    expect(ChatPresenter::message($message->fresh())['read_by_all'])->toBeTrue();
});

it('records nothing twice when the same reader reports again', function (): void {
    $member = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $room = ChatRoom::factory()->support($member)->create();

    $message = ChatMessage::factory()->from($member)->create(['chat_room_id' => $room->id]);

    $this->actingAs($admin)->post(route('chat.messages.read', $room));
    $this->actingAs($admin)->post(route('chat.messages.read', $room));

    expect(ChatMessageRead::query()->where('chat_message_id', $message->id)->count())->toBe(1);
});

it('keeps the original read time rather than moving it', function (): void {
    $member = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $room = ChatRoom::factory()->support($member)->create();

    $message = ChatMessage::factory()->from($member)->create(['chat_room_id' => $room->id]);

    $this->actingAs($admin)->post(route('chat.messages.read', $room));

    $first = ChatMessageRead::query()->sole()->read_at;

    $this->travel(5)->minutes();

    $this->actingAs($admin)->post(route('chat.messages.read', $room));

    expect(ChatMessageRead::query()->sole()->read_at->timestamp)->toBe($first->timestamp);
});

it('refuses a receipt for a message in another room', function (): void {
    $member = User::factory()->create();
    $other = User::factory()->create();
    $admin = User::factory()->admin()->create();

    $mine = ChatRoom::factory()->support($member)->create();
    $theirs = ChatRoom::factory()->support($other)->create();

    $elsewhere = ChatMessage::factory()->from($other)->create([
        'chat_room_id' => $theirs->id,
    ]);

    $this->actingAs($admin)
        ->post(route('chat.messages.read', $mine), ['message_ids' => [$elsewhere->id]])
        ->assertRedirect(route('chat.index', ['c' => $mine->uuid]));

    // The id was valid and the viewer is an admin in both rooms, so nothing but
    // the room filter can stop a receipt pointing at a message they were never
    // shown.
    expect(ChatMessageRead::query()->count())->toBe(0);
});

it('refuses a receipt from someone outside the room', function (): void {
    $member = User::factory()->create();
    $room = ChatRoom::factory()->support($member)->create();

    ChatMessage::factory()->from($member)->create(['chat_room_id' => $room->id]);

    $this->actingAs(User::factory()->create())
        ->post(route('chat.messages.read', $room))
        ->assertForbidden();

    expect(ChatMessageRead::query()->count())->toBe(0);
});

it('tells the room when a participant catches up', function (): void {
    $member = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $room = ChatRoom::factory()->support($member)->create();

    $message = ChatMessage::factory()->from($member)->create(['chat_room_id' => $room->id]);

    Event::fake([MessagesRead::class]);

    $this->actingAs($admin)->post(route('chat.messages.read', $room));

    // The sender's other tab learns about the double tick from this, not from a
    // reload, so the payload has to name the messages that completed.
    Event::assertDispatched(
        MessagesRead::class,
        fn (MessagesRead $event): bool => $event->broadcastWith()['read_by_all_ids'] === [$message->id]
            && $event->broadcastWith()['room_uuid'] === $room->uuid,
    );
});

it('stays quiet while a message is still waiting on another reader', function (): void {
    $member = User::factory()->create();
    $first = User::factory()->admin()->create();
    $second = User::factory()->admin()->create();
    $room = ChatRoom::factory()->support($member)->create();

    ChatMessage::factory()->from($member)->create(['chat_room_id' => $room->id]);

    Event::fake([MessagesRead::class]);

    $this->actingAs($first)->post(route('chat.messages.read', $room));

    // One of two admins has read it. Nothing the sender can see has changed, so
    // announcing it would be a broadcast every open thread makes on every message.
    Event::assertNotDispatched(MessagesRead::class);
});

it('broadcasts the read receipt on the room private channel', function (): void {
    $member = User::factory()->create();
    $room = ChatRoom::factory()->support($member)->create();

    $event = new MessagesRead($room, $member, []);
    $channel = new PrivateChannel(Channels::chatRoom($room));

    expect($event->broadcastOn())->toHaveCount(1)
        ->and($event->broadcastOn()[0]->name)->toBe($channel->name)
        ->and($event->broadcastAs())->toBe('chat.messages.read');
});

it('bans guests from the read receipt endpoint', function (): void {
    $member = User::factory()->create();
    $room = ChatRoom::factory()->support($member)->create();

    $this->post(route('chat.messages.read', $room))->assertRedirect(route('login'));
});

it('does not run a query per message when rendering a thread', function (): void {
    $member = User::factory()->create();
    User::factory()->admin()->create();
    $room = ChatRoom::factory()->support($member)->create();

    $count = function (int $messages) use ($room, $member): int {
        ChatMessage::query()->where('chat_room_id', $room->id)->delete();

        foreach (range(1, $messages) as $ignored) {
            ChatMessage::factory()->from($member)->create(['chat_room_id' => $room->id]);
        }

        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $this->actingAs($member)->get(route('chat.index', ['c' => $room->uuid]))->assertOk();

        return $queries;
    };

    // `isReadByAll()` reads the `reads` relation and the room's participant list,
    // so both have to be resolved once for the page rather than once per message.
    // Doubling the thread must not double the work; without that this is an N+1
    // on the most-visited screen in the app.
    expect($count(10))->toBe($count(20));
});

it('counts a message nobody else can read as read straight away', function (): void {
    $member = User::factory()->create();
    $room = ChatRoom::factory()->support($member)->create();

    // A support room with no admin in existence yet, so the member is the only
    // participant. Nobody can be waited on, and a permanent single tick on a
    // message nobody is ever going to read is a lie rather than a pending state.
    $message = ChatMessage::factory()->from($member)->create([
        'chat_room_id' => $room->id,
    ]);

    expect(ChatPresenter::message($message)['read_by_all'])->toBeTrue();
});

it('still waits for the room on a message whose author is gone', function (): void {
    $member = User::factory()->create();
    User::factory()->admin()->create();
    $room = ChatRoom::factory()->support($member)->create();

    $orphan = $room->messages()->create([
        // The author is gone; the transcript keeps the row for disputes.
        'user_id' => null,
        'body' => 'Pesan dari akun yang sudah dihapus',
    ]);

    // Nobody to exclude as the sender, so everyone in the room counts as a reader
    // and the message behaves like any other until they have all looked.
    expect(ChatPresenter::message($orphan)['read_by_all'])->toBeFalse();
});
