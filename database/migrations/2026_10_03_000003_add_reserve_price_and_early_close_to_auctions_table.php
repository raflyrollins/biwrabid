<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds the seller's optional reserve price plus the audit trail for how an
     * auction stopped accepting bids.
     */
    public function up(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            // The price a seller is willing to accept, and the floor that lets
            // them stop bidding early without handing the account to a
            // first-minute lowball bidder. Null means the seller did not set a
            // floor and may end the auction at whatever the bid stands at.
            $table->unsignedBigInteger('reserve_price')->nullable()->after('starting_price');

            // `ends_at` keeps the originally published schedule for the audit
            // trail; these two record when bidding actually stopped and why, so
            // "seller ended it early" is distinguishable from "time ran out".
            $table->timestamp('ended_at')->nullable()->after('ends_at');
            $table->string('ended_reason')->nullable()->after('ended_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->dropColumn(['reserve_price', 'ended_at', 'ended_reason']);
        });
    }
};
