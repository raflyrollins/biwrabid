<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ChatRoomType;
use App\Models\Auction;
use App\Models\ChatRoom;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creates the rooms a conversation needs, if they do not exist yet.
 *
 * Rooms are created lazily rather than by a listener on `AuctionEnded`, so an
 * auction nobody ever talks about costs nothing, and the unique constraints on
 * `chat_rooms` make a double click harmless instead of forking a thread.
 */
final class StartChat
{
    /**
     * The admin-visible thread for an auction's seller, winner and admin.
     *
     * Used for payment verification, transfer receipts and disputes.
     *
     * @return ChatRoom|null null when the auction has no winner yet
     */
    public function forAuctionGroup(Auction $auction): ?ChatRoom
    {
        return $this->auctionRoom($auction, ChatRoomType::Group);
    }

    /**
     * The private credential handoff between an auction's seller and winner.
     *
     * The admin is deliberately not a participant here: account credentials
     * pass through this thread, so it is the one room they cannot enter.
     *
     * @return ChatRoom|null null when the auction has no winner yet
     */
    public function forAuctionCredentials(Auction $auction): ?ChatRoom
    {
        return $this->auctionRoom($auction, ChatRoomType::Auction);
    }

    /**
     * Open the given kind of thread for an auction.
     *
     * Returns null when the auction has no winner yet — there is nobody to hand
     * the account over to, so no thread is opened at all. This is what keeps a
     * cancelled or bid-less auction from leaving an empty room behind.
     */
    private function auctionRoom(Auction $auction, ChatRoomType $type): ?ChatRoom
    {
        $auction->loadMissing('winner');

        if ($auction->winner_id === null || $auction->winner === null) {
            return null;
        }

        return DB::transaction(function () use ($auction, $type): ChatRoom {
            /** @var ChatRoom $room */
            $room = ChatRoom::query()->firstOrCreate(
                ['auction_id' => $auction->id, 'type' => $type],
                [],
            );

            $room->setRelation('auction', $auction);

            return $room;
        });
    }

    /**
     * The one support thread a member has with the platform admin, reused
     * across every purchase.
     */
    public function forSupport(User $initiator): ChatRoom
    {
        return DB::transaction(function () use ($initiator): ChatRoom {
            /** @var ChatRoom $room */
            $room = ChatRoom::query()->firstOrCreate(
                [
                    'type' => ChatRoomType::Support,
                    'initiator_id' => $initiator->id,
                ],
                [],
            );

            $room->setRelation('initiator', $initiator);

            return $room;
        });
    }
}
