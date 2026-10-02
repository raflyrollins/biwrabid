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
        Schema::create('chat_rooms', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->after('id');
            $table->string('type');

            // Set for `auction` rooms only; the seller/winner are derived from it.
            $table->foreignId('auction_id')
                ->nullable()
                ->index()
                ->constrained()
                ->cascadeOnDelete();

            // Set for `support` rooms only: the member who opened the thread.
            $table->foreignId('initiator_id')
                ->nullable()
                ->index()
                ->constrained('users')
                ->cascadeOnDelete();

            $table->timestamps();

            // One room per auction, so the credential thread cannot be forked.
            // PostgreSQL treats NULLs as distinct, so support rooms are unaffected.
            $table->unique('auction_id');

            // One support thread per member.
            $table->unique(['type', 'initiator_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_rooms');
    }
};
