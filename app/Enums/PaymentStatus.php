<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where the money in an auction's transaction currently sits.
 *
 * The admin is an intermediary, not a seller: the winner pays the admin's
 * account first, the admin forwards it to the seller, and the seller's
 * confirmation is what closes the auction. Nothing skips a step, because each
 * arrow is a real transfer of real money and each one needs its own evidence.
 */
enum PaymentStatus: string
{
    /**
     * The invoice is in the thread and the money has not moved yet.
     *
     * Waiting on the winner to transfer and upload their receipt.
     */
    case Requested = 'requested';

    /**
     * The admin has confirmed the winner's transfer arrived.
     *
     * Waiting on the admin to pay the seller out.
     */
    case Received = 'received';

    /**
     * The admin has uploaded proof of paying the seller.
     *
     * Waiting on the seller to confirm it landed.
     */
    case Transferred = 'transferred';

    /**
     * The seller confirmed the payout and the auction is paid.
     *
     * Terminal: nothing can move it out of here, which is what makes the
     * transition guards below safe to retry.
     */
    case Completed = 'completed';

    /**
     * Whether this status is the final one.
     */
    public function isFinal(): bool
    {
        return $this === self::Completed;
    }
}
