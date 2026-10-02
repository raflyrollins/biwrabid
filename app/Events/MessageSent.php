<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\ChatMessage;
use App\Support\Channels;
use App\Support\ChatPresenter;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Push a newly posted chat message to the room's private channel.
 *
 * Private, unlike the auction bidding channel: a credential handoff or a
 * payment proof must only reach the room's own participants, and
 * `routes/channels.php` authorises the subscription through the same
 * `ChatRoom::canAccess()` the page render uses.
 */
final class MessageSent implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ChatMessage $message) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel(Channels::chatRoom($this->message->room))];
    }

    public function broadcastAs(): string
    {
        return 'chat.message.sent';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'room_uuid' => $this->message->room->uuid,
            'message' => ChatPresenter::message($this->message),
        ];
    }
}
