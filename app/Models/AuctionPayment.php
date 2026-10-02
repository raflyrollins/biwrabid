<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The money trail for one auction, in one row.
 *
 * The chat transcript records what each party said; this records what is true.
 * Nothing in the UI or the actions infers the status from the messages, because
 * "is this auction paid?" has to be answerable by reading one column.
 *
 * RULES.md: never in a URL — the payment actions hang off the chat room — so no
 * `uuid`.
 *
 * @property int $id
 * @property int $auction_id
 * @property int $amount
 * @property PaymentStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Auction $auction
 */
#[Fillable(['auction_id', 'amount', 'status'])]
class AuctionPayment extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'status' => PaymentStatus::class,
        ];
    }

    /**
     * The auction this payment settles.
     *
     * @return BelongsTo<Auction, $this>
     */
    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
    }

    /**
     * Whether the seller has confirmed the payout and nothing is left to do.
     */
    public function isComplete(): bool
    {
        return $this->status->isFinal();
    }
}
