<?php

namespace Database\Factories;

use App\Enums\AuctionStatus;
use App\Models\Auction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Auction>
 */
class AuctionFactory extends Factory
{
    protected $model = Auction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'seller_id' => User::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'starting_price' => fake()->numberBetween(100_000, 10_000_000),
            'status' => AuctionStatus::Draft,
        ];
    }

    /**
     * An auction that is live and accepting bids.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AuctionStatus::Active,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(7),
        ]);
    }

    /**
     * An auction whose bidding window has closed.
     */
    public function ended(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AuctionStatus::Ended,
            'starts_at' => now()->subDays(8),
            'ends_at' => now()->subDay(),
        ]);
    }

    /**
     * A cancelled auction.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AuctionStatus::Cancelled,
        ]);
    }
}
