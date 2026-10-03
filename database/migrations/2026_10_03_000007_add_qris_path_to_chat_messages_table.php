<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The admin uploads their QRIS into the auction's group thread when they raise
 * the invoice, instead of the platform serving one static code from config.
 *
 * Storing it on the message rather than reading config at render time is the
 * point: the invoice is the agreement. If the platform's receiving account
 * changes six months from now, a payment that is still in flight must keep
 * showing the code the winner actually paid, not the new one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table): void {
            $table->string('qris_path')->nullable()->after('proof_path');
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table): void {
            $table->dropColumn('qris_path');
        });
    }
};
