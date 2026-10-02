<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payment coordination happens inside the auction's group thread rather than on
 * a separate checkout page, so the evidence for a transaction is the
 * conversation itself: the admin's QRIS request, the winner's transfer receipt,
 * the admin's confirmation, the admin's payout receipt, the seller's
 * confirmation.
 *
 * `kind` is what turns an ordinary transcript into that record. The alternative
 * — a separate payments table with a message reference — was rejected because
 * the audit trail a dispute needs *is* the thread, and splitting it in two means
 * reconciling two sources by hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table): void {
            // Ordinary chatter stays `message`, so the column is additive and no
            // existing row needs backfilling.
            $table->string('kind')->default('message');

            // Stored on the message rather than derived from the current
            // `Auction::paymentAmount()`: the admin fee can change between the
            // moment a request is sent and the moment it is paid, and an
            // outstanding invoice must not silently re-price itself.
            $table->bigInteger('amount')->nullable();

            // The uploaded receipt screenshot, for the two proof kinds. Null on
            // every other kind, so the front end only has to check the kind.
            $table->string('proof_path')->nullable();

            // Filters the thread down to the payment trail when a dispute needs
            // the money history without the chatter.
            $table->index(['chat_room_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table): void {
            $table->dropIndex(['chat_room_id', 'kind']);
            $table->dropColumn(['kind', 'amount', 'proof_path']);
        });
    }
};
