<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per auction: where the money in that transaction currently sits.
 *
 * The transcript says *what was said*; this says *what is true*. Deriving the
 * state by scanning messages for kinds would make "is this auction paid?" a
 * question about message ordering and phrasing, which is exactly the kind of
 * business rule that must be answerable by reading one column.
 *
 * The flow, since the admin holds the money as an intermediary:
 *
 *     requested  -> received   -> transferred -> completed
 *     (invoice)    (in to admin) (out to seller) (seller confirms)
 *
 * RULES.md: this table never appears in a URL — the actions hang off the chat
 * room — so it carries no `uuid`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auction_payments', function (Blueprint $table): void {
            $table->id();

            // One payment per auction: the unique index is what makes a double
            // click on "send the invoice" harmless.
            $table->foreignId('auction_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();

            // The invoiced total, fixed at the moment the request was sent.
            $table->bigInteger('amount');

            $table->string('status')->default('requested');

            $table->timestamps();

            // Lets the admin find everything still waiting on them without
            // loading every row and filtering in PHP.
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auction_payments');
    }
};
