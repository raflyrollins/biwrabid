<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ordinary attachments on a chat message.
 *
 * `proof_path` and `qris_path` are single files that mean something specific to
 * the payment trail. This is the opposite: anything a participant wants to show
 * the others — a screenshot of the account's stats, a photo of the goods, a PDF
 * of the specs — attached to a plain message by anyone who can post.
 *
 * A jsonb array rather than a child table: attachments are only ever read as a
 * whole, always with their message, and never queried on their own. The tradeoff
 * is that a file cannot be shared between two messages or counted across the
 * table, neither of which anything here needs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table): void {
            $table->jsonb('attachments')->nullable()->after('qris_path');
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table): void {
            $table->dropColumn('attachments');
        });
    }
};
