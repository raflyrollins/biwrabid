<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\CloseAuction;
use App\Models\Auction;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class CloseExpiredAuctions extends Command
{
    protected $signature = 'auctions:close-expired';

    protected $description = 'Close active auctions whose bidding window has ended';

    public function handle(CloseAuction $closeAuction): int
    {
        $closed = 0;

        Auction::query()
            ->active()
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function (Collection $auctions) use ($closeAuction, &$closed): void {
                foreach ($auctions as $auction) {
                    if ($closeAuction($auction) !== null) {
                        $closed++;
                    }
                }
            });

        $this->components->info("Closed {$closed} expired auction(s).");

        return self::SUCCESS;
    }
}
