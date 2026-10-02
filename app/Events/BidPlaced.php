<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Auction;
use App\Models\Bid;
use App\Support\AuctionConfig;
use App\Support\BidPresenter;
use App\Support\Channels;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast to everyone watching an auction the moment a new bid lands.
 *
 * `ShouldDispatchAfterCommit` keeps the broadcast off the wire until the
 * surrounding bid transaction actually commits, so a rolled-back bid never
 * reaches a watching browser.
 */
final class BidPlaced implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Auction $auction,
        public Bid $bid,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel(Channels::auction($this->auction))];
    }

    public function broadcastAs(): string
    {
        return 'bid.placed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'auction_uuid' => $this->auction->uuid,
            'bid' => BidPresenter::summary($this->bid),
            'current_price' => $this->auction->current_price,
            'current_price_label' => AuctionConfig::price($this->auction->effectivePrice()),
            'minimum_next_bid' => $this->auction->minimumNextBid(),
            'minimum_next_bid_label' => AuctionConfig::price($this->auction->minimumNextBid()),
        ];
    }
}
