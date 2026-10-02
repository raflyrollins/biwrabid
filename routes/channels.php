<?php

declare(strict_types=1);

use App\Models\ChatRoom;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function (User $user, int $id): bool {
    return (int) $user->id === (int) $id;
});

/*
 * Private chat rooms. The channel name is produced by Channels::chatRoom() and
 * must stay in sync with it.
 *
 * Authorisation defers to ChatRoom::canAccess(), the same predicate the
 * ChatRoomPolicy and the page render use, so a socket subscription can never be
 * wider than the page that links to it.
 */
Broadcast::channel('chat.rooms.{roomUuid}', function (User $user, string $roomUuid): bool {
    $room = ChatRoom::query()
        ->where('uuid', $roomUuid)
        ->with(['auction', 'initiator'])
        ->first();

    return $room !== null && $room->canAccess($user);
});
