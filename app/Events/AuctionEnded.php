<?php

declare(strict_types=1);

namespace App\Events;

use App\Enums\CloseReason;
use App\Models\Auction;
use App\Support\AuctionConfig;
use App\Support\Channels;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast when an auction's bidding window closes and a winner is recorded.
 *
 * Carries the `CloseReason` so the detail page can tell bidders the difference
 * between "the timer ran out" and "the seller ended it early".
 */
final class AuctionEnded implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Auction $auction,
        public CloseReason $reason = CloseReason::TimeExpired,
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
        return 'auction.ended';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $this->auction->loadMissing('winner');

        return [
            'auction_uuid' => $this->auction->uuid,
            'status' => $this->auction->status->value,
            'ended_reason' => $this->reason->value,
            'winner_name' => $this->auction->winner?->name,
            'current_price' => $this->auction->current_price,
            'current_price_label' => AuctionConfig::price($this->auction->effectivePrice()),
        ];
    }
}
