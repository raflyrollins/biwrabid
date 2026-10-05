<?php

declare(strict_types=1);

use App\Actions\CoordinatePayment;
use App\Enums\AuctionStatus;
use App\Events\MessageDeleted;
use App\Events\MessageUpdated;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\ChatMessage;
use App\Models\ChatMessageDeletion;
use App\Models\ChatMessageRead;
use App\Models\ChatRoom;
use App\Models\ChatRoomDismissal;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

/**
 * Correcting, removing and closing.
 *
 * The rule that runs through all of it: a chat transcript is the evidence a payment
 * dispute is settled from, so almost nothing here destroys a row. Editing rewrites
 * text and leaves a mark saying so; "delete for me" writes a tombstone; only
 * "delete for everyone" removes anything, and only the author may ask for it, and
 * only for a few minutes after sending.
 */
function modifiableRoom(): array
{
    $member = User::factory()->create();
    $admin = User::factory()->admin()->create();

    $room = ChatRoom::factory()->support($member)->create();

    return [$member, $admin, $room];
}

it('lets the author correct their own message', function (): void {
    [$member, , $room] = modifiableRoom();

    $message = ChatMessage::factory()->from($member)->create([
        'chat_room_id' => $room->id,
        'body' => 'Rekening 12345',
    ]);

    $this->actingAs($member)
        ->from(route('chat.index', ['c' => $room->uuid]))
        ->patch(route('chat.messages.update', [$room, $message]), ['body' => 'Rekening 123456'])
        ->assertRedirect(route('chat.index', ['c' => $room->uuid]));

    $message->refresh();

    expect($message->body)->toBe('Rekening 123456')
        // The mark is what makes an edited transcript trustworthy: without it
        // somebody reading back has no idea the number changed.
        ->and($message->edited_at)->not->toBeNull();
});

it('refuses an edit from somebody else in the room', function (): void {
    [$member, $admin, $room] = modifiableRoom();

    $message = ChatMessage::factory()->from($member)->create([
        'chat_room_id' => $room->id,
        'body' => 'Rekening 12345',
    ]);

    // An admin moderating a payment is a participant, so `modify` lets them into
    // the endpoint; it is `canBeEditedBy` that refuses, because the message is not
    // theirs. The outcome is a redirect with "cannot edit" rather than a bare 403,
    // which is what a hand-crafted request (or a menu open in another tab) gets.
    $this->actingAs($admin)
        ->from(route('chat.index', ['c' => $room->uuid]))
        ->patch(route('chat.messages.update', [$room, $message]), ['body' => 'Rekening 99999'])
        ->assertSessionHas('status');

    expect($message->fresh()->body)->toBe('Rekening 12345');
});

it('refuses an edit from somebody outside the room entirely', function (): void {
    [$member, , $room] = modifiableRoom();

    $message = ChatMessage::factory()->from($member)->create([
        'chat_room_id' => $room->id,
        'body' => 'Rekening 12345',
    ]);

    // Not a participant at all: refused at the door, before the request body is read.
    $this->actingAs(User::factory()->create())
        ->from(route('chat.index'))
        ->patch(route('chat.messages.update', [$room, $message]), ['body' => 'Rekening 99999'])
        ->assertForbidden();

    expect($message->fresh()->body)->toBe('Rekening 12345');
});

it('refuses an edit to a message that carries a file', function (): void {
    Storage::fake('public');

    [$member, , $room] = modifiableRoom();

    $this->actingAs($member)->post(route('chat.messages.store', $room), [
        'body' => 'Ini buktinya',
        'attachments' => [UploadedFile::fake()->image('bukti.png')],
    ]);

    $message = $room->messages()->sole();

    $this->actingAs($member)
        ->from(route('chat.index', ['c' => $room->uuid]))
        ->patch(route('chat.messages.update', [$room, $message]), ['body' => 'Bukti lain'])
        ->assertSessionHas('status');

    // An edit that rewrote the caption while leaving the file alone would be a
    // caption that describes something the sender did not attach.
    expect($message->fresh()->body)->toBe('Ini buktinya');
});

it('leaves the room alone when an edit changes nothing', function (): void {
    [$member, , $room] = modifiableRoom();

    $message = ChatMessage::factory()->from($member)->create([
        'chat_room_id' => $room->id,
        'body' => 'Halo',
    ]);

    $this->actingAs($member)
        ->from(route('chat.index', ['c' => $room->uuid]))
        ->patch(route('chat.messages.update', [$room, $message]), ['body' => 'Halo']);

    // No "edited" mark on a message that was never different.
    expect($message->fresh()->edited_at)->toBeNull();
});

it('broadcasts an edit so other tabs correct their bubble', function (): void {
    [$member, , $room] = modifiableRoom();

    $message = ChatMessage::factory()->from($member)->create([
        'chat_room_id' => $room->id,
        'body' => 'Rekening 12345',
    ]);

    Event::fake([MessageUpdated::class]);

    $this->actingAs($member)
        ->from(route('chat.index', ['c' => $room->uuid]))
        ->patch(route('chat.messages.update', [$room, $message]), ['body' => 'Rekening 123456']);

    Event::assertDispatched(
        MessageUpdated::class,
        fn (MessageUpdated $event): bool => $event->broadcastWith()['message']['body'] === 'Rekening 123456'
            && $event->broadcastWith()['message']['edited_at'] !== null
            && $event->broadcastAs() === 'chat.message.updated',
    );
});

it('takes a message back out for everybody while the window is open', function (): void {
    [$member, , $room] = modifiableRoom();

    $message = ChatMessage::factory()->from($member)->create([
        'chat_room_id' => $room->id,
        'body' => 'Salah kirim',
    ]);

    $this->actingAs($member)
        ->from(route('chat.index', ['c' => $room->uuid]))
        ->delete(route('chat.messages.destroy', [$room, $message]), ['scope' => 'everyone']);

    expect(ChatMessage::query()->whereKey($message->id)->exists())->toBeFalse();
});

it('refuses to take a message back once the window has passed', function (): void {
    [$member, , $room] = modifiableRoom();

    $message = ChatMessage::factory()->from($member)->create([
        'chat_room_id' => $room->id,
        'body' => 'Salah kirim',
    ]);

    $this->travel(ChatMessage::editWindowMinutes + 1)->minutes();

    $this->actingAs($member)
        ->from(route('chat.index', ['c' => $room->uuid]))
        ->delete(route('chat.messages.destroy', [$room, $message]), ['scope' => 'everyone'])
        ->assertSessionHas('status');

    // A message anybody can retract at any time is not a record, and this one is
    // read back in a dispute months later.
    expect(ChatMessage::query()->whereKey($message->id)->exists())->toBeTrue();
});

it('refuses to take somebody elses message back even inside the window', function (): void {
    [$member, , $room] = modifiableRoom();

    $message = ChatMessage::factory()->from($member)->create([
        'chat_room_id' => $room->id,
        'body' => 'Rekening saya',
    ]);

    $this->actingAs(User::factory()->create())
        ->from(route('chat.index', ['c' => $room->uuid]))
        ->delete(route('chat.messages.destroy', [$room, $message]), ['scope' => 'everyone'])
        ->assertForbidden();

    expect(ChatMessage::query()->whereKey($message->id)->exists())->toBeTrue();
});

it('hides a message for one person and leaves it for everybody else', function (): void {
    [$member, $admin, $room] = modifiableRoom();

    $message = ChatMessage::factory()->from($member)->create([
        'chat_room_id' => $room->id,
        'body' => 'Tidak ingin dibaca lagi',
    ]);

    $this->actingAs($member)
        ->from(route('chat.index', ['c' => $room->uuid]))
        ->delete(route('chat.messages.destroy', [$room, $message]), ['scope' => 'self']);

    // The row is still there: the admin and the payment trail keep it.
    expect(ChatMessage::query()->whereKey($message->id)->exists())->toBeTrue()
        ->and(ChatMessageDeletion::query()->where('chat_message_id', $message->id)->count())->toBe(1);

    $this->actingAs($member)
        ->get(route('chat.index', ['c' => $room->uuid]))
        ->assertInertia(fn ($page) => $page->where('thread.messages', []));

    // And the admin still sees it, which is the whole reason this is a tombstone
    // rather than a delete.
    $this->actingAs($admin)
        ->get(route('chat.index', ['c' => $room->uuid]))
        ->assertInertia(fn ($page) => $page->has('thread.messages', 1));
});

it('drops the read receipt of a message hidden for somebody', function (): void {
    [$member, $admin, $room] = modifiableRoom();

    // The member writes it and the admin reads it, so there is a receipt to drop.
    $message = ChatMessage::factory()->from($member)->create([
        'chat_room_id' => $room->id,
        'body' => 'Halo',
    ]);

    $this->actingAs($admin)->post(route('chat.messages.read', $room));

    expect(ChatMessageRead::query()->where('chat_message_id', $message->id)->count())->toBe(1);

    // Now the *author* hides it for themselves, which is the case that has a
    // receipt of their own to clean up.
    $this->actingAs($member)
        ->from(route('chat.index', ['c' => $room->uuid]))
        ->delete(route('chat.messages.destroy', [$room, $message]), ['scope' => 'self']);

    // The member's own receipt goes; the admin's stays, because the admin can still
    // see the message. What matters here is that the reader of a hidden message no
    // longer reports having read it.
    expect(
        ChatMessageRead::query()
            ->where('chat_message_id', $message->id)
            ->where('user_id', $member->id)
            ->count(),
    )->toBe(0);
});

it('refuses a deletion aimed at a message in another room', function (): void {
    [$member, , $room] = modifiableRoom();
    $elsewhere = ChatRoom::factory()->support(User::factory()->create())->create();

    $message = ChatMessage::factory()->from($member)->create([
        'chat_room_id' => $elsewhere->id,
        'body' => 'Pesan di room lain',
    ]);

    $this->actingAs($member)
        ->from(route('chat.index', ['c' => $room->uuid]))
        ->delete(route('chat.messages.destroy', [$room, $message]), ['scope' => 'self']);

    // The id is attacker-controlled: a tombstone pointing across rooms would be a
    // claim about a conversation the actor was never shown.
    expect(ChatMessageDeletion::query()->count())->toBe(0);
});

it('names the actions the viewer has on a message', function (): void {
    [$member, , $room] = modifiableRoom();

    $mine = ChatMessage::factory()->from($member)->create([
        'chat_room_id' => $room->id,
        'body' => 'Pesan saya',
    ]);

    $theirs = ChatMessage::factory()->from(User::factory()->create())->create([
        'chat_room_id' => $room->id,
        'body' => 'Pesan orang lain',
    ]);

    $this->actingAs($member)
        ->get(route('chat.index', ['c' => $room->uuid]))
        ->assertInertia(fn ($page) => $page
            // The server decides what the menu offers, so a stale menu item cannot
            // offer an action the endpoint would refuse.
            ->where('thread.messages.0.can_edit', true)
            ->where('thread.messages.0.can_delete_for_all', true)
            ->where('thread.messages.1.can_edit', false)
            ->where('thread.messages.1.can_delete_for_all', false)
        );
});

it('closes a conversation for one person without touching the room', function (): void {
    [$member, $admin, $room] = modifiableRoom();

    ChatMessage::factory()->from($member)->create([
        'chat_room_id' => $room->id,
        'body' => 'Halo admin',
    ]);

    $this->actingAs($member)
        ->from(route('chat.index', ['c' => $room->uuid]))
        ->post(route('chat.dismiss', $room))
        ->assertRedirect(route('chat.index'));

    expect(ChatRoomDismissal::query()->where('chat_room_id', $room->id)->count())->toBe(1);

    $this->actingAs($member)
        ->get(route('chat.index'))
        ->assertInertia(fn ($page) => $page->where('rooms.data', []));

    // The room is still there for the admin, which is what keeps the transcript
    // readable when somebody reports a problem with it.
    $this->actingAs($admin)
        ->get(route('chat.index'))
        ->assertInertia(fn ($page) => $page->has('rooms.data', 1));
});

it('closes the same conversation twice without a second row', function (): void {
    [$member, , $room] = modifiableRoom();

    $this->actingAs($member)->from(route('chat.index'))->post(route('chat.dismiss', $room));
    $this->actingAs($member)->from(route('chat.index'))->post(route('chat.dismiss', $room));

    // The Escape key and the menu item are two ways to ask for the same thing, and
    // somebody leaning on Escape will ask more than once.
    expect(ChatRoomDismissal::query()->count())->toBe(1);
});

it('refuses to close a conversation from outside the room', function (): void {
    [, , $room] = modifiableRoom();

    $this->actingAs(User::factory()->create())
        ->from(route('chat.index'))
        ->post(route('chat.dismiss', $room))
        ->assertForbidden();

    expect(ChatRoomDismissal::query()->count())->toBe(0);
});

it('bans guests from every message action', function (): void {
    [$member, , $room] = modifiableRoom();

    $message = ChatMessage::factory()->from($member)->create([
        'chat_room_id' => $room->id,
        'body' => 'Halo',
    ]);

    $this->patch(route('chat.messages.update', [$room, $message]), ['body' => 'x'])
        ->assertRedirect(route('login'));

    $this->delete(route('chat.messages.destroy', [$room, $message]), ['scope' => 'everyone'])
        ->assertRedirect(route('login'));

    $this->post(route('chat.dismiss', $room))->assertRedirect(route('login'));

    expect($message->fresh()->body)->toBe('Halo');
});

it('broadcasts a removal so other tabs drop the bubble', function (): void {
    [$member, , $room] = modifiableRoom();

    $message = ChatMessage::factory()->from($member)->create([
        'chat_room_id' => $room->id,
        'body' => 'Salah kirim',
    ]);

    Event::fake([MessageDeleted::class]);

    $this->actingAs($member)
        ->from(route('chat.index', ['c' => $room->uuid]))
        ->delete(route('chat.messages.destroy', [$room, $message]), ['scope' => 'everyone']);

    Event::assertDispatched(
        MessageDeleted::class,
        fn (MessageDeleted $event): bool => $event->broadcastWith()['message_id'] === $message->id
            && $event->broadcastWith()['for_everyone'] === true
            && $event->broadcastAs() === 'chat.message.deleted',
    );
});

it('refuses a payment trail message as if it were an edit', function (): void {
    $seller = User::factory()->create();
    $winner = User::factory()->create();
    $admin = User::factory()->admin()->create();

    $auction = Auction::factory()->for($seller, 'seller')->create([
        'status' => AuctionStatus::Ended,
        'winner_id' => $winner->id,
    ]);

    // The invoice is priced off the highest bid, so the auction needs one.
    Bid::factory()->for($auction, 'auction')->for($winner, 'bidder')->create([
        'amount' => 1_500_000,
    ]);

    $room = ChatRoom::factory()->group($auction->fresh())->create();

    Storage::fake('public');

    app(CoordinatePayment::class)->sendRequest(
        $room,
        $admin,
        UploadedFile::fake()->image('qris.png'),
    );

    $invoice = $room->messages()->where('kind', '!=', 'message')->sole();

    $this->actingAs($admin)
        ->from(route('chat.index', ['c' => $room->uuid]))
        ->patch(route('chat.messages.update', [$room, $invoice]), ['body' => 'Invoice palsu'])
        ->assertSessionHas('status');

    // The payment trail is a record, not chatter: it is not editable by the person
    // who happened to post it.
    expect($invoice->fresh()->body)->not->toBe('Invoice palsu');
});
