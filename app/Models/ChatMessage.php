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
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['body', 'user_id', 'kind', 'amount', 'proof_path'])]
class ChatMessage extends Model
{
    /** @use HasFactory<ChatMessageFactory> */
    use HasFactory;

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
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
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
