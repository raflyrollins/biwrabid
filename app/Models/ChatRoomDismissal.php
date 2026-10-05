<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One person's decision that a conversation is not for them.
 *
 * A tombstone rather than a delete: the room keeps existing for the admin, the
 * seller and the winner, because the transcript is what a payment dispute is
 * settled from. This row only removes it from the dismisser's own list, and it is
 * deliberately *not* reversible in the app — "unhide everything I ever closed"
 * is not a feature, it is a second inbox nobody asked for.
 *
 * No factory: the row is only ever written by `firstOrCreate()` from the endpoint
 * that closes a conversation, and a factory would be a second way to make one.
 *
 * @see chat_room_dismissals migration for why this is not a column
 */
class ChatRoomDismissal extends Model
{
    protected $fillable = [
        'chat_room_id',
        'user_id',
    ];

    /**
     * @return BelongsTo<ChatRoom, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(ChatRoom::class, 'chat_room_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
