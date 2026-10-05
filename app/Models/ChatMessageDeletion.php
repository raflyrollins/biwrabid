<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One person's decision that a single message is not for them.
 *
 * The per-message version of `ChatRoomDismissal`, and for the same reason: the row
 * on `chat_messages` stays, because it is evidence. This only keeps it out of one
 * reader's transcript, which is what "delete for me" means on a message that other
 * people are still looking at.
 *
 * No factory: written only by `firstOrCreate()` from the endpoint that hides a
 * message, and a factory would be a second way to make one.
 *
 * @see chat_message_deletions migration for why this is not a column
 */
class ChatMessageDeletion extends Model
{
    protected $fillable = [
        'chat_message_id',
        'user_id',
    ];

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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
