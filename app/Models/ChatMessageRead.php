<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One participant having read one message.
 *
 * The tick state in the thread is derived from these rows rather than stored on
 * the message: "read by everyone" depends on who *else* is in the room, and
 * membership is derived (`ChatRoom::roleFor()`) rather than stored, so a column
 * on `chat_messages` would drift from the room it claims to describe.
 *
 * RULES.md: never in a URL, so no `uuid`.
 *
 * @property int $id
 * @property int $chat_message_id
 * @property int $user_id
 * @property CarbonImmutable $read_at
 */
#[Fillable(['chat_message_id', 'user_id', 'read_at'])]
class ChatMessageRead extends Model
{
    /**
     * A receipt is a fact about a moment and is never edited, so there is no
     * `updated_at` to rewrite every time someone re-opens the thread.
     */
    public $timestamps = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'chat_message_id' => 'integer',
            'user_id' => 'integer',
            'read_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ChatMessage, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'chat_message_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
