<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Auction;
use App\Models\ChatRoom;

/**
 * Builds the Reverb/Echo channel names.
 *
 * RULES.md — "No hardcoding" requires channel strings to be produced by a
 * helper rather than interpolated at the call site, so the broadcast side and
 * the channel-authorisation callback in `routes/channels.php` cannot drift
 * apart. The `{uuid}` placeholder shape is fixed by these two methods.
 *
 * @see RULES.md — "No hardcoding"
 */
final class Channels
{
    /**
     * The public channel carrying live bidding for one auction.
     */
    public static function auction(Auction $auction): string
    {
        return 'auctions.'.$auction->uuid;
    }

    /**
     * The private channel carrying chat traffic for one room.
     */
    public static function chatRoom(ChatRoom $room): string
    {
        return 'chat.rooms.'.$room->uuid;
    }
}
