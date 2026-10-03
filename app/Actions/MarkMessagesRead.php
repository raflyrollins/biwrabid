<?php

declare(strict_types=1);

namespace App\Actions;

use App\Events\MessagesRead;
use App\Models\ChatMessage;
use App\Models\ChatMessageRead;
use App\Models\ChatRoom;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Records that a participant has seen messages in a thread.
 *
 * Writes receipts rather than advancing a "last read" pointer on the room,
 * because a room has several people in it at once and each of them needs their
 * own progress: the double tick on a message is "everybody *else* has read
 * this", which one shared cursor cannot answer.
 *
 * Idempotent, and cheap when it has to be — the client calls this on every new
 * message that arrives while the thread is open, so most calls find nothing new
 * to record and write nothing at all.
 */
final class MarkMessagesRead
{
    /**
     * Record receipts for the messages the viewer has seen, and tell the room
     * when that completes a message's read state.
     *
     * Exactly those messages, not everything before them: the client only claims
     * what it actually displayed. A null list means the whole room, which is what
     * a client sends when it has no reason to be specific.
     *
     * The viewer must be a participant. Re-checked here rather than trusted from
     * the caller, for the same reason `CoordinatePayment` re-checks its actor:
     * this action writes a row that other participants' clients render as a tick.
     *
     * @param  Collection<int, ChatMessage>|null  $messages  the messages the
     *                                                       viewer has actually seen; null resolves the whole room
     */
    public function __invoke(ChatRoom $room, User $viewer, ?Collection $messages = null): void
    {
        if ($room->roleFor($viewer) === null) {
            return;
        }

        $seen = $this->unreadBy($room, $viewer, $messages);

        if ($seen->isEmpty()) {
            return;
        }

        $now = now();

        DB::transaction(function () use ($seen, $viewer, $now): void {
            foreach ($seen as $message) {
                ChatMessageRead::query()->firstOrCreate(
                    [
                        'chat_message_id' => $message->id,
                        'user_id' => $viewer->id,
                    ],
                    ['read_at' => $now],
                );
            }
        });

        $completed = $this->nowReadByAll($room, $seen);

        // Nothing completed means no tick changed anywhere in the room, and this
        // endpoint is called on every message that arrives while the thread is
        // open — so the common case broadcasts nothing at all.
        if ($completed === []) {
            return;
        }

        MessagesRead::dispatch($room, $viewer, $completed);
    }

    /**
     * Which of the messages just receipted are now read by every participant.
     *
     * Asked after the writes rather than before, because the row this reader has
     * just written is the one that can tip a message over: until the last
     * missing reader looks, the sender is still waiting.
     *
     * Reuses `ChatMessage::isReadByAll()` rather than repeating the comparison, so
     * the tick the broadcast announces and the tick the thread renders cannot
     * disagree. One extra query for the whole batch — the freshly written receipts
     * are not on the relation the messages were loaded with, and re-reading them
     * per message is exactly the N+1 the eager load exists to avoid.
     *
     * @param  Collection<int, ChatMessage>  $seen
     * @return array<int, int>
     */
    private function nowReadByAll(ChatRoom $room, Collection $seen): array
    {
        $reads = ChatMessageRead::query()
            ->whereIn('chat_message_id', $seen->modelKeys())
            ->get()
            ->groupBy('chat_message_id');

        $completed = [];

        foreach ($seen as $message) {
            $message->setRelation(
                'reads',
                $reads->get($message->id, new Collection),
            );
            $message->setRelation('room', $room);

            if ($message->isReadByAll()) {
                $completed[] = $message->id;
            }
        }

        return $completed;
    }

    /**
     * The messages the viewer has seen but has no receipt for, excluding their
     * own.
     *
     * Their own messages are skipped rather than receipted: a sender has read
     * their own message by definition, and `ChatRoom::expectedReaderIds()`
     * excludes them for the same reason. Recording it would add a row nothing
     * reads.
     *
     * `sender` rides along because the read state of a message is relative to who
     * sent it — the sender is the one reader a double tick never waits for.
     *
     * @param  Collection<int, ChatMessage>|null  $messages
     * @return Collection<int, ChatMessage>
     */
    private function unreadBy(ChatRoom $room, User $viewer, ?Collection $messages): Collection
    {
        $query = $room->messages()->with('sender');

        if ($messages !== null) {
            $ids = $messages->modelKeys();

            if ($ids === []) {
                // Nothing was named, so nothing is unread — and the caller gets
                // its own (empty) collection back rather than a second one.
                return $messages;
            }

            $query->whereIn('id', $ids);
        }

        return $query
            ->where('user_id', '!=', $viewer->id)
            ->whereDoesntHave('reads', function ($reads) use ($viewer): void {
                $reads->where('user_id', $viewer->id);
            })
            ->get();
    }
}
