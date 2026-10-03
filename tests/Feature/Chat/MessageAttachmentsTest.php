<?php

declare(strict_types=1);

use App\Enums\ChatMessageKind;
use App\Models\ChatRoom;
use App\Models\User;
use App\Support\ChatPresenter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Ordinary chat attachments: images and PDFs a member attaches to a plain
 * message.
 *
 * The interesting rule here is that the caption is optional when a file came
 * along. A screenshot of an account's stats rarely needs words, and the request
 * used to insist on both — which made "attach a photo" impossible without also
 * inventing something to type.
 */
function attachmentScenario(): array
{
    $member = User::factory()->create();
    $admin = User::factory()->admin()->create();

    return [$member, $admin, ChatRoom::factory()->support($member)->create()];
}

function image(string $name = 'bukti.png'): UploadedFile
{
    return UploadedFile::fake()->image($name, 400, 800);
}

function document(string $name = 'spec.pdf'): UploadedFile
{
    return UploadedFile::fake()->create($name, 120, 'application/pdf');
}

beforeEach(function (): void {
    Storage::fake('public');
});

it('stores an image against the message and hands back its url', function (): void {
    [$member, , $room] = attachmentScenario();

    $this->actingAs($member)
        ->post(route('chat.messages.store', $room), [
            'body' => 'Ini screenshottedya',
            'attachments' => [image()],
        ])
        ->assertRedirect(route('chat.index', ['c' => $room->uuid]));

    $message = $room->messages()->sole();

    expect($message->kind)->toBe(ChatMessageKind::Message)
        ->and($message->body)->toBe('Ini screenshottedya');

    $attachment = $message->attachmentList()[0];

    // Only the three fields the thread renders: the stored path stays on the row
    // and never travels to the client, which builds no path of its own.
    expect($attachment)->toHaveKeys(['name', 'size', 'url'])
        ->and($attachment['name'])->toBe('bukti.png')
        ->and($attachment['size'])->toBeGreaterThan(0)
        ->and($attachment['url'])->toStartWith('/storage/chat-attachments/');

    $path = $message->attachments[0]['path'];

    expect(Storage::disk('public')->exists($path))->toBeTrue()
        ->and($attachment['url'])->toContain($path);
});

it('accepts a pdf and keeps its name for the reader', function (): void {
    [$member, , $room] = attachmentScenario();

    $this->actingAs($member)
        ->post(route('chat.messages.store', $room), [
            'body' => 'Spesinya saya lampirkan',
            'attachments' => [document()],
        ]);

    // A PDF has nothing to show as a thumbnail, so the thread renders a labelled
    // card from the name: losing it would leave an unlabelled box.
    expect($room->messages()->sole()->attachmentList()[0]['name'])->toBe('spec.pdf');
});

it('accepts an attachment with no caption at all', function (): void {
    [$member, , $room] = attachmentScenario();

    $this->actingAs($member)
        ->post(route('chat.messages.store', $room), ['attachments' => [image()]])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('chat.index', ['c' => $room->uuid]));

    // The browser posts an empty string for a caption the reader never typed, and
    // `ConvertEmptyStringsToNull` turns that into null before validation runs. The
    // rule has to accept null for the upload to survive, or attaching a photo
    // needs a sentence nobody wanted to write.
    expect($room->messages()->sole()->body)->toBe('')
        ->and($room->messages()->sole()->attachmentList())->toHaveCount(1);
});

it('still refuses a message with neither words nor files', function (): void {
    [$member, , $room] = attachmentScenario();

    $this->actingAs($member)
        ->post(route('chat.messages.store', $room), ['body' => '   '])
        ->assertSessionHasErrors('body');

    expect($room->messages()->count())->toBe(0);
});

it('refuses a file type that is neither an image nor a pdf', function (): void {
    [$member, , $room] = attachmentScenario();

    $this->actingAs($member)
        ->post(route('chat.messages.store', $room), [
            'body' => 'coba lampirkan',
            'attachments' => [UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream')],
        ])
        ->assertSessionHasErrors('attachments.0');

    expect($room->messages()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles('chat-attachments'))->toBe([]);
});

it('bounds how many files one message may carry', function (): void {
    config()->set('chat.attachments.max_per_message', 2);

    [$member, , $room] = attachmentScenario();

    $this->actingAs($member)
        ->post(route('chat.messages.store', $room), [
            'body' => 'banyak nih',
            'attachments' => [image('a.png'), image('b.png'), image('c.png')],
        ])
        ->assertSessionHasErrors('attachments');

    expect($room->messages()->count())->toBe(0);
});

it('bounds the size of one file', function (): void {
    config()->set('chat.attachments.max_size_kb', 100);

    [$member, , $room] = attachmentScenario();

    $this->actingAs($member)
        ->post(route('chat.messages.store', $room), [
            'body' => 'kegedean',
            'attachments' => [UploadedFile::fake()->create('besar.png', 500, 'image/png')],
        ])
        ->assertSessionHasErrors('attachments.0');

    expect($room->messages()->count())->toBe(0);
});

it('keeps attachments out of a room the sender is not in', function (): void {
    [$member, , $room] = attachmentScenario();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->post(route('chat.messages.store', $room), ['attachments' => [image()]])
        ->assertForbidden();

    expect($room->messages()->count())->toBe(0);
});

it('shows the files on a message the reader can open', function (): void {
    [$member, , $room] = attachmentScenario();

    $this->actingAs($member)->post(route('chat.messages.store', $room), [
        'body' => 'lampirannya',
        'attachments' => [image(), document()],
    ]);

    $this->actingAs($member)
        ->get(route('chat.index', ['c' => $room->uuid]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('chat/index')
            ->has('thread.messages', 1)
            ->has('thread.messages.0.attachments', 2)
            ->where('thread.messages.0.attachments.0.name', 'bukti.png')
            ->where('thread.messages.0.attachments.1.name', 'spec.pdf')
        );
});

it('keeps an attachment list off a message that has none', function (): void {
    [$member, , $room] = attachmentScenario();

    $message = $room->messages()->create([
        'user_id' => $member->id,
        'body' => 'tanpa lampiran',
    ]);

    // An empty list rather than null, so the thread can render the grid
    // unconditionally instead of guarding every message.
    expect($message->attachmentList())->toBe([])
        ->and(ChatPresenter::message($message)['attachments'])->toBe([]);
});

it('defaults an ordinary message to the message kind', function (): void {
    [$member, , $room] = attachmentScenario();

    $message = $room->messages()->create([
        'user_id' => $member->id,
        'body' => 'halo',
    ]);

    // The column default never reaches the model instance that just wrote the
    // row, so without the attribute default `kind` reads back as null and the
    // presenter fatals on `$message->kind->value`.
    expect($message->kind)->toBe(ChatMessageKind::Message)
        ->and(ChatPresenter::message($message)['kind'])->toBe('message');
});
