<?php

declare(strict_types=1);

use App\Enums\ChatMessageKind;
use App\Enums\ChatRoomType;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\User;

/**
 * The member's support thread, created by the first message rather than by the
 * button that opens the composer.
 *
 * A thread nobody has written in is not a conversation. Created on the click, it
 * lands in the admin's inbox as an empty row per member who ever looked at the
 * page, indistinguishable from the ones waiting for a reply — so the room is
 * written at the same moment as the message that gives it a reason to exist.
 */

/*
 * Whether pressing "Chat with Admin" writes anything.
 *
 * Asserted on the room count rather than on the response: the whole point is
 * that opening the composer is free.
 */
it('writes nothing when the member only opens the composer', function (): void {
    $member = User::factory()->create();
    User::factory()->admin()->create();

    $this->actingAs($member)
        ->get(route('chat.index', ['new' => 'support']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('chat/index')
            // No room, and a draft the composer can post into.
            ->where('room', null)
            ->where('thread', null)
            ->where('draft', 'support')
        );

    expect(ChatRoom::query()->count())->toBe(0)
        ->and(ChatMessage::query()->count())->toBe(0);
});

it('creates the room and the first message together', function (): void {
    $member = User::factory()->create();
    User::factory()->admin()->create();

    $this->actingAs($member)
        ->post(route('chat.support'), ['body' => 'Halo, saya butuh bantuan pembayaran.'])
        ->assertRedirect();

    $room = ChatRoom::query()->sole();

    expect($room->type)->toBe(ChatRoomType::Support)
        ->and($room->initiator_id)->toBe($member->id)
        // The redirect names the room it just created, so the member lands in the
        // conversation they started rather than back on an empty list.
        ->and($room->messages()->sole()->body)->toBe('Halo, saya butuh bantuan pembayaran.');

    $this->assertTrue(
        ChatRoom::query()->sole()->messages()->sole()->is($member),
    );
});

it('lands the member in the conversation it just started', function (): void {
    $member = User::factory()->create();
    User::factory()->admin()->create();

    $this->actingAs($member)
        ->post(route('chat.support'), ['body' => 'Halo admin'])
        ->assertRedirect(route('chat.index', [
            'c' => ChatRoom::query()->sole()->uuid,
        ]));
});

it('reuses the one support thread a member already has', function (): void {
    $member = User::factory()->create();
    User::factory()->admin()->create();

    $existing = ChatRoom::factory()->support($member)->create();
    ChatMessage::factory()->from($member)->create([
        'chat_room_id' => $existing->id,
        'body' => 'Halo admin',
    ]);

    $this->actingAs($member)
        ->post(route('chat.support'), ['body' => 'Satu pertanyaan lagi'])
        ->assertRedirect(route('chat.index', ['c' => $existing->uuid]));

    // One thread per member, reused across every purchase: a second conversation
    // with the same admin would only split the history in two.
    expect(ChatRoom::query()->count())->toBe(1)
        ->and($existing->messages()->count())->toBe(2);
});

/*
 * An empty submit must not create the room.
 *
 * The request validates before the controller runs, so this is the case where
 * pressing send with nothing written leaves no trace at all - which is the whole
 * reason the room is created here rather than when the composer is opened.
 */
it('creates nothing when the first message is empty', function (): void {
    $member = User::factory()->create();
    User::factory()->admin()->create();

    $this->actingAs($member)
        ->post(route('chat.support'), ['body' => '   '])
        ->assertSessionHasErrors('body');

    expect(ChatRoom::query()->count())->toBe(0)
        ->and(ChatMessage::query()->count())->toBe(0);
});

it('starts the thread from an attachment alone', function (): void {
    $member = User::factory()->create();
    User::factory()->admin()->create();

    // A screenshot of an account is the common case, and it needs no caption.
    $this->actingAs($member)
        ->post(route('chat.support'), ['body' => '', 'attachments' => [UploadedFile::fake()->image('bukti.png')]])
        ->assertSessionHasNoErrors();

    expect(ChatRoom::query()->count())->toBe(1)
        ->and(ChatRoom::query()->sole()->messages()->sole()->kind)->toBe(ChatMessageKind::Message);
});

it('refuses to let an admin open a support thread', function (): void {
    $admin = User::factory()->admin()->create();

    // The admin is the other side of every support thread, so there is nobody for
    // one to be opened with.
    $this->actingAs($admin)
        ->post(route('chat.support'), ['body' => 'Halo'])
        ->assertForbidden();

    expect(ChatRoom::query()->count())->toBe(0);
});

it('bans guests from starting a support thread', function (): void {
    $this->post(route('chat.support'), ['body' => 'Halo'])->assertRedirect(route('login'));

    expect(ChatRoom::query()->count())->toBe(0);
});

it('ignores the draft for an admin', function (): void {
    $admin = User::factory()->admin()->create();
    ChatRoom::factory()->support(User::factory()->create())->create();

    // The composer for a thread the admin cannot have is not offered, and asking
    // for it by hand gets the plain list rather than a dead end.
    $this->actingAs($admin)
        ->get(route('chat.index', ['new' => 'support']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('draft', null));
});

it('prefers the open room over a draft', function (): void {
    $member = User::factory()->create();
    User::factory()->admin()->create();

    $room = ChatRoom::factory()->support($member)->create();

    // `?new=support&c={uuid}` is a conversation, not a half-started one beside it.
    $this->actingAs($member)
        ->get(route('chat.index', ['c' => $room->uuid, 'new' => 'support']))
        ->assertInertia(fn ($page) => $page
            ->where('room.uuid', $room->uuid)
            ->where('draft', null)
        );
});

it('keeps the message rules identical to a thread reply', function (): void {
    $member = User::factory()->create();
    User::factory()->admin()->create();

    $room = ChatRoom::factory()->support($member)->create();

    // The first message is validated by the same rules as any other, or "attach a
    // photo" would work in a thread and fail on the very first send.
    $this->actingAs($member)
        ->post(route('chat.support'), [
            'body' => 'Halo',
            'attachments' => [UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream')],
        ])
        ->assertSessionHasErrors('attachments.0');

    $this->actingAs($member)
        ->post(route('chat.messages.store', $room), [
            'body' => 'Halo',
            'attachments' => [UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream')],
        ])
        ->assertSessionHasErrors('attachments.0');
});