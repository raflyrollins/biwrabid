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

    /**
     * Editing or removing a message needs to be in the room.
     *
     * Deliberately the room, not the message: whether *this* message may be
     * corrected or retracted is the author's business and the window's, and both of
     * those live on `ChatMessage` where `ManageMessage` re-checks them. A policy
     * that also decided that would be the third place the same question is asked,
     * and the one most likely to disagree with the other two.
     *
     * An admin is a participant like anybody else here, which is the point: being
     * able to read a thread is not permission to rewrite it.
     */
    public function modify(User $user, ChatRoom $room): bool
    {
        return $room->canAccess($user);
    }
}
