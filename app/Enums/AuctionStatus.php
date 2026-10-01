<?php

declare(strict_types=1);

namespace App\Enums;

enum AuctionStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Ended = 'ended';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
}
