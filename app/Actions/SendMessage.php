<?php

declare(strict_types=1);

namespace App\Actions;

use App\Events\MessageSent;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Appends a message to a room and pushes it to the room's private channel.
 */
final class SendMessage
{
    /**
     * @throws \RuntimeException when the sender is not a participant; the
     *                           policy and the channel callback already reject
     *                           this, so reaching it means a missed check.
     */
    public function __invoke(ChatRoom $room, User $sender, string $body): ChatMessage
    {
        $message = DB::transaction(function () use ($room, $sender, $body): ChatMessage {
            /** @var ChatMessage $message */
            $message = $room->messages()->create([
                'user_id' => $sender->id,
                'body' => $body,
            ]);

            // Touch the room so the room list orders by latest activity.
            $room->forceFill(['updated_at' => now()])->saveQuietly();

            $message->setRelation('room', $room);

            return $message;
        });

        MessageSent::dispatch($message);

        return $message;
    }
}
