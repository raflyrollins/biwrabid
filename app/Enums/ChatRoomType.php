<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The kinds of conversation the platform runs.
 *
 * @see RULES.md — "No hardcoding": the `chat_rooms.type` column is always
 *               compared against one of these cases, never a bare string.
 */
enum ChatRoomType: string
{
    /**
     * A one-to-one thread between a member and the platform admin, used for
     * payment (the admin holds the receiving account) and disputes.
     */
    case Support = 'support';

    /**
     * The auction's coordination thread: seller, winner and the admin together.
     *
     * Used for everything that needs a witness — payment verification, transfer
     * receipts, disputes. The admin reads and replies here.
     */
    case Group = 'group';

    /**
     * The private credential handoff between an auction's seller and winner.
     *
     * No admin is admitted, deliberately. Account credentials pass through this
     * room, and an admin who could read it would be a single point of failure
     * for every account on the platform. Anything the admin needs to see about
     * the transaction belongs in the group room instead.
     */
    case Auction = 'auction';

    /**
     * Whether the platform admin is a participant in this kind of room.
     *
     * The credential room is the one room they are kept out of, so this is
     * what keeps `ChatRoom::roleFor()` honest.
     */
    public function admitsAdmin(): bool
    {
        return $this !== self::Auction;
    }
}
