<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who has read which message.
 *
 * The single-tick / double-tick in the thread needs to answer "has everyone
 * *else* in this room seen this message?", and that cannot be answered from a
 * timestamp on the message itself: a room has two or three other people in it
 * (seller, winner, and the admin where the kind admits them), and the last one
 * to look is the one who completes the double tick.
 *
 * A child table rather than a `read_at` column for that reason, and rather than
 * a `last_read_message_id` cursor on the room because a cursor cannot say *who*
 * has read: two participants sharing one room would race to overwrite each
 * other's progress, and the admin joining a group thread later would either be
 * counted retroactively or silently ignored.
 *
 * Rows are written when a participant has the thread open, so this is a
 * read-tracking table and not an audit log — deleting one loses a tick, never
 * evidence. `cascadeOnDelete` on the message is right for the same reason: a
 * read receipt for a message that no longer exists has nothing to attach to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_message_reads', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('chat_message_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->timestamp('read_at');

            // One receipt per person per message. This is the lookup the thread
            // performs — "these messages, who read them" — so the pair is the
            // index, and its leading column already covers the message side.
            $table->unique(['chat_message_id', 'user_id']);

            // The other direction: everything this person has read. The message
            // id is the leading column of the unique pair, so `user_id` needs its
            // own index rather than the pair covering it.
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_message_reads');
    }
};
