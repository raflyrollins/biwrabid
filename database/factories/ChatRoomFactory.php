<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ChatRoomType;
use App\Models\Auction;
use App\Models\ChatRoom;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatRoom>
 */
class ChatRoomFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => ChatRoomType::Support,
            'auction_id' => null,
            'initiator_id' => User::factory(),
        ];
    }

    /**
     * The private seller <-> winner credential thread. The admin is not a member.
     */
    public function auction(Auction $auction): static
    {
        return $this->state(fn (): array => [
            'type' => ChatRoomType::Auction,
            'auction_id' => $auction->id,
            'initiator_id' => null,
        ]);
    }

    /**
     * The admin-visible thread for an auction's seller, winner and admin.
     */
    public function group(Auction $auction): static
    {
        return $this->state(fn (): array => [
            'type' => ChatRoomType::Group,
            'auction_id' => $auction->id,
            'initiator_id' => null,
        ]);
    }

    public function support(User $initiator): static
    {
        return $this->state(fn (): array => [
            'type' => ChatRoomType::Support,
            'auction_id' => null,
            'initiator_id' => $initiator->id,
        ]);
    }
}
