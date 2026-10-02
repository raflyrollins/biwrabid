<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Why an auction stopped accepting bids.
 *
 * Both cases run through the same `CloseAuction` action, so the winner
 * selection and the `AuctionEnded` broadcast can never diverge between them.
 */
enum CloseReason: string
{
    /** The published `ends_at` passed and the scheduler closed it. */
    case TimeExpired = 'time_expired';

    /** The seller stopped bidding early and the top bidder won on the spot. */
    case SellerEnded = 'seller_ended';
}
