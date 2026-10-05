<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a message's text was last corrected.
 *
 * The thread renders "diubah" beside a message that has one, and it needs to know
 * that from the message rather than from an audit table: the flag is part of what
 * the message *is* on screen, and every reader of a transcript needs it.
 *
 * Kept separate from `updated_at`, which moves for reasons that have nothing to do
 * with an edit — a read receipt touching the room, a payment message updating its
 * amount. Using `updated_at` would put a false "edited" mark on ordinary chatter.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table): void {
            $table->timestamp('edited_at')->nullable()->after('attachments');
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table): void {
            $table->dropColumn('edited_at');
        });
    }
};
