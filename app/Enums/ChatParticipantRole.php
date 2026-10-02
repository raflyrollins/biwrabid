<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a participant relates to a room.
 *
 * Drives both the policy and the UI: the bubble colour, the name badge, and
 * the Indonesian labels shown next to each sender.
 */
enum ChatParticipantRole: string
{
    /** A member talking to the admin outside any auction. */
    case Member = 'member';

    /** The seller of the auction a room belongs to. */
    case Seller = 'seller';

    /** The recorded winner of the auction a room belongs to. */
    case Winner = 'winner';

    /** A platform admin, who can join and moderate every room. */
    case Admin = 'admin';
}
