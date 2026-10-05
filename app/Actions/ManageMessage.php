<?php

declare(strict_types=1);

namespace App\Actions;

use App\Events\MessageDeleted;
use App\Events\MessageUpdated;
use App\Models\ChatMessage;
use App\Models\ChatMessageDeletion;
use App\Models\ChatRoom;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Correcting and removing a message the viewer wrote.
 *
 * Three operations that look like one feature and are deliberately not one:
 *
 * - **Edit** rewrites the text everybody reads. The author only, and never on a
 *   message with attachments, because an edit that leaves the old screenshot in
 *   place is worse than no edit at all.
 * - **Delete for everybody** removes the row. Author only, inside a short window —
 *   see `ChatMessage::isWithinEditWindow()` — because a message anybody can retract
 *   at any time is not a record.
 * - **Delete for me** writes a tombstone and leaves the row alone, so the other
 *   people in the room, and the payment trail, still have it.
 *
 * The author is re-checked here rather than trusted from the caller, for the same
 * reason `CoordinatePayment` re-checks its actor: an action that erases somebody
 * else's words must not depend on its caller having checked.
 */
final class ManageMessage
{
    /**
     * Correct the text of a message.
     *
     * Returns null when the edit does not apply, which is what makes a stale menu
     * item safe: the row did not change, so nothing is announced to anybody.
     */
    public function edit(ChatRoom $room, ChatMessage $message, User $actor, string $body): ?ChatMessage
    {
        if (! $this->inRoom($room, $message) || ! $message->canBeEditedBy($actor)) {
            return null;
        }

        $body = trim($body);

        // Nothing to correct, or a "correction" to the same words: writing
        // `edited_at` for either would put a "diubah" mark on a message that was
        // never actually different.
        if ($body === '' || $body === trim($message->body)) {
            return null;
        }

        $message->forceFill([
            'body' => $body,
            'edited_at' => now(),
        ])->save();

        $message->setRelation('room', $room);

        MessageUpdated::dispatch($room, $message, $actor);

        return $message;
    }

    /**
     * Take a message back out of the conversation for everybody.
     *
     * The id is read out before the row goes, because the broadcast carries it:
     * `SerializesModels` would try to load a model that no longer exists.
     */
    public function deleteForAll(ChatRoom $room, ChatMessage $message, User $actor): bool
    {
        if (! $this->inRoom($room, $message) || ! $message->canBeDeletedForAllBy($actor)) {
            return false;
        }

        $id = $message->id;

        DB::transaction(function () use ($room, $message): void {
            // The receipts go with it: `cascadeOnDelete` would take them anyway, and
            // doing it inside the transaction means the room is never momentarily
            // holding a message whose read state was already lost.
            $message->reads()->delete();
            $message->deletions()->delete();
            $message->delete();

            $room->forceFill(['updated_at' => now()])->saveQuietly();
        });

        MessageDeleted::dispatch($room, $id, $actor, forEveryone: true);

        return true;
    }

    /**
     * Hide a message from this viewer's transcript, leaving it in the room.
     */
    public function deleteForSelf(ChatRoom $room, ChatMessage $message, User $actor): bool
    {
        if (! $this->inRoom($room, $message)) {
            return false;
        }

        DB::transaction(function () use ($message, $actor): void {
            ChatMessageDeletion::query()->firstOrCreate([
                'chat_message_id' => $message->id,
                'user_id' => $actor->id,
            ]);

            // A message nobody can see has no ticks to report, and reporting it
            // anyway would tell the sender somebody read something nobody did.
            $message->reads()->where('user_id', $actor->id)->delete();
        });

        MessageDeleted::dispatch($room, $message->id, $actor, forEveryone: false);

        return true;
    }

    /**
     * Whether this endpoint's message actually belongs to the room it was sent to.
     *
     * The id is attacker-controlled, and a tombstone pointing across rooms would be
     * a claim about a conversation the actor was never shown.
     */
    private function inRoom(ChatRoom $room, ChatMessage $message): bool
    {
        return $message->chat_room_id === $room->id;
    }
}
