<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();

            // RULES.md: `chat_messages` never appears in a URL, so it carries
            // no `uuid` column.
            $table->foreignId('chat_room_id')
                ->index()
                ->constrained()
                ->cascadeOnDelete();

            // Kept when the sender's account is deleted: the transcript is
            // evidence for disputes and payment checks, so it must survive.
            $table->foreignId('user_id')
                ->nullable()
                ->index()
                ->constrained()
                ->nullOnDelete();

            $table->text('body');
            $table->timestamps();

            // Backs the "load the thread in order, newest page first" query.
            $table->index(['chat_room_id', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
