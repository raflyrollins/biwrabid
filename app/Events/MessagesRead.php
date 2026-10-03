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
 * Tells a room that one of its participants has caught up on the thread.
 *
 * Sent so the *sender's* other tab can turn a single tick into a double one
 * without a reload.
 *
 * The payload carries only the messages that are now read by everybody, not the
 * ones this reader scrolled past. A client cannot work the rest out for itself:
 * "read by everyone" depends on the room's membership, and that decision belongs
 * in one place on the server — so a message that is still waiting on a second
 * reader would arrive as a no-op the sender's tab has nothing to do with.
 */
final class MessagesRead implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<int, int>  $readByAllIds  the messages whose last missing
     *                                         receipt this reader just supplied
     */
    public function __construct(
        public ChatRoom $room,
        public User $reader,
        public array $readByAllIds,
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
        return 'chat.messages.read';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'room_uuid' => $this->room->uuid,
            'reader_id' => $this->reader->id,
            'read_by_all_ids' => $this->readByAllIds,
        ];
    }
}
