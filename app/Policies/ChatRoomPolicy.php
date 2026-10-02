<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ChatParticipantRole;
use App\Models\ChatRoom;
use App\Models\User;

/**
 * Room access is derived by `ChatRoom::roleFor()`, so the policy stays thin and
 * the HTTP controller, the page render and the private broadcast channel all
 * agree by construction.
 */
final class ChatRoomPolicy
{
    public function view(User $user, ChatRoom $room): bool
    {
        return $room->canAccess($user);
    }

    public function reply(User $user, ChatRoom $room): bool
    {
        return $room->canAccess($user);
    }

    /**
     * Only admins moderate rooms.
     */
    public function moderate(User $user, ChatRoom $room): bool
    {
        return $room->roleFor($user) === ChatParticipantRole::Admin;
    }
}
