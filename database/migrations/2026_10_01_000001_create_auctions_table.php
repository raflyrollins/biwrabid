<?php

declare(strict_types=1);

use App\Enums\AuctionStatus;
use App\Support\SearchConfig;
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
        $columns = SearchConfig::columnsFor('auctions');

        Schema::create('auctions', function (Blueprint $table) use ($columns) {
            $table->id();
            $table->uuid('uuid')->unique()->after('id');
            $table->foreignId('seller_id')->index()->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description');
            $table->unsignedBigInteger('starting_price');
            $table->unsignedBigInteger('current_price')->nullable();
            $table->string('status')->default(AuctionStatus::Draft->value);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'ends_at']);
            $table->fullText($columns)->language(SearchConfig::language());
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auctions');
    }
};
