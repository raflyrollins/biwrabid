<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\User;
use App\Support\Channels;
use App\Support\ChatPresenter;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The text of a message changed.
 *
 * Sent so the other tabs in the room correct their bubble instead of leaving a
 * wrong number on screen until somebody reloads. Same room channel and the same
 * authorisation as `MessageSent`, for the same reason: this is a payment receipt
 * being corrected as often as it is a typo.
 */
final class MessageUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ChatRoom $room,
        public ChatMessage $message,
        public User $actor,
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
        return 'chat.message.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'room_uuid' => $this->room->uuid,
            'actor_id' => $this->actor->id,
            'message' => ChatPresenter::message($this->message),
        ];
    }
}
