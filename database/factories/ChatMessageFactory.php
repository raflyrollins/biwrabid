<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ChatMessageKind;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatMessage>
 */
class ChatMessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'chat_room_id' => ChatRoom::factory(),
            'user_id' => User::factory(),
            'body' => fake()->sentence(),
            'kind' => ChatMessageKind::Message,
        ];
    }

    public function from(User $user): static
    {
        return $this->state(fn (): array => [
            'user_id' => $user->id,
        ]);
    }

    public function kind(ChatMessageKind $kind): static
    {
        return $this->state(fn (): array => [
            'kind' => $kind,
        ]);
    }

    /**
     * A transfer receipt, with the file already written.
     *
     * Tests exercise the actions rather than hand-building these rows, so the
     * factory state exists mainly for arranging a room that is already part-way
     * through the trail.
     */
    public function proof(string $path = 'chat-proofs/receipt.jpg'): static
    {
        return $this->state(fn (): array => [
            'kind' => ChatMessageKind::PaymentProof,
            'proof_path' => $path,
        ]);
    }
}
