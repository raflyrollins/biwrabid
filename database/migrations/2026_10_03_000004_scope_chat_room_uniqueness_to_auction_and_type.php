<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An auction now runs two rooms — the admin-visible group thread and the
 * seller <-> winner credential handoff — so the "one room per auction" unique
 * index has to key on the type as well. Otherwise the first room created would
 * block the second forever.
 *
 * This is an index change rather than a new unique on a changed column, so it
 * is a separate migration instead of an edit to the create: the existing rows
 * are already valid under the new rule and nothing needs backfilling.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_rooms', function (Blueprint $table): void {
            $table->dropUnique('chat_rooms_auction_id_unique');
            $table->unique(['auction_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::table('chat_rooms', function (Blueprint $table): void {
            $table->dropUnique(['auction_id', 'type']);
            $table->unique('auction_id');
        });
    }
};
