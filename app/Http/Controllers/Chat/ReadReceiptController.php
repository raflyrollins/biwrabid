<?php

declare(strict_types=1);

namespace App\Http\Controllers\Chat;

use App\Actions\MarkMessagesRead;
use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\MarkMessagesReadRequest;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;

/**
 * Records that the viewer has caught up on a thread.
 *
 * This is what turns the single tick on a sender's own message into a double one
 * on their other device, and it is a POST rather than a GET because it writes.
 * There is no flash: the visible effect is a tick somewhere else, and a modal
 * announcing "marked as read" would be noise.
 */
class ReadReceiptController extends Controller
{
    public function store(MarkMessagesReadRequest $request, ChatRoom $chatRoom, MarkMessagesRead $markRead): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $ids = $request->validated('message_ids');

        $markRead(
            $chatRoom,
            $user,
            is_array($ids) ? $this->messagesInRoom($chatRoom, $ids) : null,
        );

        // Back to the workspace with the same room open, not to the referrer: the
        // thread is where the reader actually is, and naming the room means the
        // redirect lands on the conversation rather than on the list.
        return redirect()->route('chat.index', ['c' => $chatRoom->uuid]);
    }

    /**
     * The requested messages, but only those that really belong to this room.
     *
     * A receipt points at a message through a foreign key, so accepting an id
     * from another thread would write a row claiming this room's participant read
     * a message they were never shown. Filtering here rather than trusting the
     * list keeps that impossible.
     *
     * @param  array<int, mixed>  $ids
     * @return Collection<int, ChatMessage>
     */
    private function messagesInRoom(ChatRoom $chatRoom, array $ids): Collection
    {
        $ids = array_values(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $ids),
            static fn (int $id): bool => $id > 0,
        ));

        return $chatRoom->messages()
            ->whereIn('id', $ids)
            ->get();
    }
}
