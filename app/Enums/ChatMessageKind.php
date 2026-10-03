<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a chat message is for.
 *
 * `Message` is ordinary chatter and is the overwhelming majority. The rest are
 * the payment trail, which is why they live in the transcript: a dispute is
 * settled by reading the thread, and evidence that is filed somewhere else is
 * evidence that gets lost.
 *
 * @see PaymentStatus for the order these appear in
 */
enum ChatMessageKind: string
{
    /** Ordinary text. */
    case Message = 'message';

    /**
     * The admin's invoice: the QRIS they uploaded plus the amount owed.
     *
     * Authored by the admin in the auction's *group* room, never the credential
     * room. Carries `qris_path`.
     */
    case PaymentRequest = 'payment_request';

    /**
     * The winner's receipt for paying the admin.
     *
     * Carries `proof_path`.
     */
    case PaymentProof = 'payment_proof';

    /**
     * The admin has checked their own account and confirms the money arrived.
     *
     * Carries no file: the proof is the admin's word, deliberately, because the
     * admin is the one holding the account.
     */
    case PaymentReceived = 'payment_received';

    /**
     * The admin's receipt for paying the seller out.
     *
     * Carries `proof_path` — the winner never sees this direction, but the
     * seller and the admin do, and that is what the seller confirms against.
     */
    case TransferProof = 'transfer_proof';

    /**
     * The seller confirms the payout landed. Completes the auction.
     *
     * This message is the reason the auction is marked paid: the platform does
     * not decide on the seller's behalf that their money arrived.
     */
    case PaymentCompleted = 'payment_completed';

    /**
     * Whether this kind carries an uploaded file in `proof_path`.
     */
    public function carriesProof(): bool
    {
        return $this === self::PaymentProof || $this === self::TransferProof;
    }

    /**
     * Whether this kind carries the receiving account in `qris_path`.
     *
     * Separate from `carriesProof()` because it is a different column and a
     * different meaning: this one is what the winner pays *to*, not evidence
     * that they already did.
     */
    public function carriesQrIs(): bool
    {
        return $this === self::PaymentRequest;
    }

    /**
     * Whether this kind is part of the payment trail rather than chatter.
     *
     * Used to filter the money history out of the chat transcript view.
     */
    public function isPayment(): bool
    {
        return $this !== self::Message;
    }
}
