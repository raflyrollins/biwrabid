<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\ChatRoom;
use App\Models\User;
use App\Support\Channels;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A message left the conversation, for everybody or for one person.
 *
 * One event for both cases, with `for_everyone` saying which, because the client
 * does the same thing with them — drop the bubble — and the only difference is
 * whether it may also drop it from a room it is about to reload. Two events would
 * have been two handlers for one line of behaviour.
 *
 * The message is passed as an id rather than a model: in the "everybody" case the
 * row is already gone, and `SerializesModels` would try to load it again.
 */
final class MessageDeleted implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ChatRoom $room,
        public int $messageId,
        public User $actor,
        public bool $forEveryone,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel(Channels::chatRoom($this->room))];
    }

    public function broadcastAs(): string
    {
        return 'chat.message.deleted';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'room_uuid' => $this->room->uuid,
            'message_id' => $this->messageId,
            'actor_id' => $this->actor->id,
            'for_everyone' => $this->forEveryone,
        ];
    }
}
