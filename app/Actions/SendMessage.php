<?php

declare(strict_types=1);

namespace App\Actions;

use App\Events\MessageSent;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\User;
use App\Support\ChatConfig;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Appends a message to a room and pushes it to the room's private channel.
 */
final class SendMessage
{
    /**
     * @param  string|null  $body  `null` when the composer was submitted with no
     *                             caption; `required_without:attachments` is what
     *                             rejects that when no file came along.
     * @param  array<int, UploadedFile>  $attachments
     *
     * @throws \RuntimeException when the sender is not a participant; the
     *                           policy and the channel callback already reject
     *                           this, so reaching it means a missed check. Also
     *                           thrown when an attachment cannot be written, in
     *                           which case no message is posted at all.
     */
    public function __invoke(ChatRoom $room, User $sender, ?string $body, array $attachments = []): ChatMessage
    {
        $body = trim((string) $body);
        $stored = $this->store($attachments);

        try {
            $message = DB::transaction(function () use ($room, $sender, $body, $stored): ChatMessage {
                /** @var ChatMessage $message */
                $message = $room->messages()->create([
                    'user_id' => $sender->id,
                    'body' => $body,
                    'attachments' => $stored === [] ? null : $stored,
                ]);

                // Touch the room so the room list orders by latest activity.
                $room->forceFill(['updated_at' => now()])->saveQuietly();

                $message->setRelation('room', $room);

                // Nobody has read a message that was just written, so the double
                // tick is false by construction. Setting the relation answers
                // `isReadByAll()` without the query it would otherwise make on
                // every single send.
                $message->setRelation('reads', new Collection);

                return $message;
            });
        } catch (\Throwable $e) {
            // The files are written before the transaction opens, so a failed
            // post has to take them back down with it.
            $this->discard($stored);

            throw $e;
        }

        MessageSent::dispatch($message);

        return $message;
    }

    /**
     * Write every attachment and return what to persist on the message.
     *
     * The original filename is kept for display only — it is never used to build
     * a path, so a crafted name cannot escape the attachment directory.
     *
     * A write failure throws rather than returning what it managed to store: the
     * attachments *are* the message when there is no body, so quietly dropping
     * them would post an empty bubble and tell the sender it had been sent.
     *
     * @param  array<int, UploadedFile>  $files
     * @return array<int, array{path: string, name: string, size: int}>
     *
     * @throws \RuntimeException
     */
    private function store(array $files): array
    {
        if ($files === []) {
            return [];
        }

        $disk = Storage::disk(ChatConfig::attachmentsDisk());
        $stored = [];

        foreach ($files as $file) {
            // `store()` reports failure by returning false, and writing that
            // into the column would produce a message pointing at nothing.
            $path = $file->store(ChatConfig::attachmentsDirectory(), ChatConfig::attachmentsDisk());

            if ($path === false) {
                $this->discard($stored);

                throw new \RuntimeException('The chat attachment could not be stored.');
            }

            $stored[] = [
                'path' => $path,
                'name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
            ];
        }

        return $stored;
    }

    /**
     * @param  array<int, array{path: string, name: string, size: int}>  $stored
     */
    private function discard(array $stored): void
    {
        $disk = Storage::disk(ChatConfig::attachmentsDisk());

        foreach ($stored as $attachment) {
            $disk->delete($attachment['path']);
        }
    }
}
