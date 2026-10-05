<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ChatMessageKind;
use App\Enums\ChatParticipantRole;
use App\Support\ChatConfig;
use Carbon\CarbonImmutable;
use Database\Factories\ChatMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * A single chat message.
 *
 * Most are ordinary text. `kind` marks the payment trail — the invoice, the two
 * transfer receipts, and the confirmations — which is why those live in the
 * transcript at all: a dispute is settled by reading the thread.
 *
 * RULES.md: `chat_messages` never appears in a URL, so it has no `uuid`.
 *
 * @property int $id
 * @property int $chat_room_id
 * @property int|null $user_id
 * @property string $body
 * @property ChatMessageKind $kind
 * @property int|null $amount
 * @property string|null $proof_path
 * @property string|null $qris_path
 * @property array<int, array{path: string, name: string, size: int}>|null $attachments
 * @property CarbonImmutable|null $edited_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['body', 'user_id', 'kind', 'amount', 'proof_path', 'qris_path', 'attachments'])]
class ChatMessage extends Model
{
    /** @use HasFactory<ChatMessageFactory> */
    use HasFactory;

    /**
     * How long a sender may correct or retract their own message, in minutes.
     *
     * One number for both operations because they answer the same question — "is
     * this still fresh enough that nobody has built on it?" — and two deadlines
     * would only differ by accident.
     */
    public const int editWindowMinutes = 10;

    /**
     * Defaults for attributes the column only sets on the database side.
     *
     * The `kind` column has a `'message'` default in the migration, but a
     * database default never reaches the model instance that just wrote the
     * row: `create()` hands back an object whose `kind` is null until it is
     * re-read, and a payment message is broadcast the moment it is created. The
     * enum cast then turns `$message->kind->carriesProof()` into a fatal on null.
     *
     * This is the same trap as `User::$attributes` for `is_admin`.
     *
     * @var array<string, string>
     */
    protected $attributes = [
        'kind' => 'message',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ChatMessageKind::class,
            'amount' => 'integer',
            'attachments' => 'array',
            // Cast like the other timestamps so `edited_at !== null` is the whole
            // check — an uncast string would compare truthily for `''`.
            'edited_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * The files attached to this message, with their public URLs resolved.
     *
     * Only the stored path, the original name and the byte size are persisted;
     * the URL is derived here so a change of disk or directory is a config edit
     * rather than a migration over every row.
     *
     * @return array<int, array{name: string, size: int, url: string}>
     */
    public function attachmentList(): array
    {
        $attachments = $this->attachments;

        if ($attachments === null) {
            return [];
        }

        $disk = Storage::disk(ChatConfig::attachmentsDisk());
        $resolved = [];

        foreach ($attachments as $attachment) {
            $resolved[] = [
                'name' => $attachment['name'],
                'size' => $attachment['size'],
                'url' => $disk->url($attachment['path']),
            ];
        }

        return $resolved;
    }

    /**
     * The public URL of the uploaded receipt, if this message carries one.
     *
     * Guarded on the kind, so a stray `proof_path` on ordinary chatter cannot
     * turn into a link.
     */
    public function proofUrl(): ?string
    {
        if ($this->proof_path === null || ! $this->kind->carriesProof()) {
            return null;
        }

        return Storage::disk(ChatConfig::proofsDisk())->url($this->proof_path);
    }

    /**
     * The receiving account image, if this message is the invoice.
     *
     * Read from the stored file rather than from config, because this is the code
     * the winner was actually asked to pay. Guarded on the kind for the same
     * reason `proofUrl()` is.
     */
    public function qrisUrl(): ?string
    {
        if ($this->qris_path === null || ! $this->kind->carriesQrIs()) {
            return null;
        }

        return Storage::disk(ChatConfig::proofsDisk())->url($this->qris_path);
    }

    /**
     * The read receipts on this message.
     *
     * @return HasMany<ChatMessageRead, $this>
     */
    public function reads(): HasMany
    {
        return $this->hasMany(ChatMessageRead::class, 'chat_message_id');
    }

    /**
     * The people who have hidden this message from their own transcript.
     *
     * @return HasMany<ChatMessageDeletion, $this>
     */
    public function deletions(): HasMany
    {
        return $this->hasMany(ChatMessageDeletion::class, 'chat_message_id');
    }

    /**
     * Whether this person has deleted this message for themselves.
     */
    public function isHiddenFor(?User $viewer): bool
    {
        if ($viewer === null) {
            return false;
        }

        $deletions = $this->relationLoaded('deletions')
            ? $this->deletions
            : $this->deletions()->where('user_id', $viewer->id)->get();

        return $deletions->contains('user_id', $viewer->id);
    }

    /**
     * How long ago this message was written, against the window in which the
     * author may still take it back for everybody.
     *
     * A message that can be retracted outright needs a deadline, and a long one
     * means a long stretch of time in which the sender and the reader are looking
     * at different threads. Ten minutes is short enough that somebody still has the
     * thread open to notice, and long enough to cover a mistyped number that
     * somebody had to go and check.
     */
    public function isWithinEditWindow(): bool
    {
        return $this->created_at !== null
            && $this->created_at->diffInMinutes(now()) < self::editWindowMinutes;
    }

    /**
     * Whether this person may correct the text of this message.
     *
     * The author only, and only while the message has no attachments: an edit that
     * silently leaves the old screenshot in place would be worse than no edit at
     * all, and the alternative — swapping files out from under the people reading
     * them — is not something this app should do without saying so.
     */
    public function canBeEditedBy(?User $viewer): bool
    {
        if ($viewer === null || $this->user_id === null) {
            return false;
        }

        if ($this->user_id !== $viewer->id) {
            return false;
        }

        if ($this->kind !== ChatMessageKind::Message) {
            return false;
        }

        return $this->attachments === null || $this->attachments === [];
    }

    /**
     * Whether this person may take this message back for everybody.
     *
     * Only the author, and only inside the window. An admin moderating a dispute is
     * a separate question answered by the payment trail rather than by erasing the
     * words somebody else wrote.
     */
    public function canBeDeletedForAllBy(?User $viewer): bool
    {
        if ($viewer === null || $this->user_id === null || $this->user_id !== $viewer->id) {
            return false;
        }

        return $this->isWithinEditWindow();
    }

    /**
     * Whether every other participant in this room has read the message.
     *
     * This is the double tick. It is derived rather than stored because "every
     * other participant" is a property of the room, and membership is derived
     * too (`ChatRoom::participantIds()`), so a column on this table would have to
     * be recomputed every time somebody joins — and would be wrong for a room
     * whose membership changes after the message was sent.
     *
     * Reads the `reads` relation, so a thread renders in one query by
     * eager-loading it rather than one per message. A message that nobody else
     * can read — a deleted sender, or a room with no other participant — counts
     * as read: there is no one left to wait for, and a permanent single tick on
     * an unreachable message is a lie.
     */
    public function isReadByAll(): bool
    {
        $room = $this->room;

        if ($room === null) {
            return false;
        }

        $expected = $room->expectedReaderIds($this->sender);

        if ($expected === []) {
            return true;
        }

        $readBy = $this->reads
            ->pluck('user_id')
            ->map(static fn (int $id): int => $id)
            ->all();

        return array_diff($expected, $readBy) === [];
    }

    /**
     * @return BelongsTo<ChatRoom, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(ChatRoom::class, 'chat_room_id');
    }

    /**
     * The author. Null once their account has been deleted, which is why the
     * transcript keeps the row.
     *
     * The foreign key is named explicitly: `belongsTo()` alone would infer
     * `sender_id` from the method name, not the `user_id` column.
     *
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * How the author relates to the room this message was posted in.
     *
     * Derived through `ChatRoom::roleFor()` so the badge on a message and the
     * membership check on the room are the same decision.
     */
    public function senderRole(): ChatParticipantRole
    {
        $sender = $this->sender;

        if ($sender === null) {
            return ChatParticipantRole::Member;
        }

        $room = $this->room;

        if ($room === null) {
            return ChatParticipantRole::Member;
        }

        // Ask the room rather than re-deriving it: an admin who somehow posted
        // in a credential thread is still labelled correctly, and the
        // seller/winner checks cannot drift from `ChatRoom::roleFor()`.
        return $room->roleFor($sender) ?? ChatParticipantRole::Member;
    }
}
