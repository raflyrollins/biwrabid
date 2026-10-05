<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Delete this chat for me" — on a whole conversation, and on a single message.
 *
 * Two tables rather than a `deleted_at` on `chat_rooms` / `chat_messages`,
 * because deleting for yourself is a statement about *one person's* view of the
 * thread. Everyone else in the room still has it: the transcript is the evidence a
 * payment dispute is settled from, so a column on the row would mean one person
 * erasing it for the seller, the winner and the admin at once.
 *
 * Which is also why these are separate tables rather than one table with two
 * nullable foreign keys: a row here is always about exactly one of the two, and a
 * half-populated row with both null is a shape nothing can use.
 *
 * `cascadeOnDelete` because a tombstone for a message that no longer exists has
 * nothing to attach to — the hard delete already answered that question.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_room_dismissals', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('chat_room_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->timestamps();

            // Dismissing the same room twice is a no-op rather than a second row,
            // which is what makes the endpoint idempotent.
            $table->unique(['chat_room_id', 'user_id']);

            // The other direction: "which rooms has this person closed". The unique
            // pair leads with the room, so the person side needs its own index.
            $table->index('user_id');
        });

        Schema::create('chat_message_deletions', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('chat_message_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['chat_message_id', 'user_id']);

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_message_deletions');
        Schema::dropIfExists('chat_room_dismissals');
    }
};
