<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Typed access to `config/chat.php`.
 *
 * @see AuctionConfig — the same pattern for `config/auction.php`
 * @see RULES.md — "No hardcoding"
 */
final class ChatConfig
{
    public static function messageMaxLength(): int
    {
        return (int) config('chat.message_max_length');
    }

    public static function messagesPerPage(): int
    {
        return (int) config('chat.messages_per_page');
    }

    public static function roomsPerPage(): int
    {
        return (int) config('chat.rooms_per_page');
    }

    /**
     * How many receipts the winner or the admin may attach per direction.
     *
     * A cap rather than a total: one direction is enough for most transactions,
     * and a fixed ceiling stops a thread from being used as bulk file storage.
     */
    public static function maxProofsPerStep(): int
    {
        return (int) config('chat.proofs.max_per_step', 3);
    }

    public static function maxProofSizeKb(): int
    {
        return (int) config('chat.proofs.max_size_kb');
    }

    /**
     * @return array<int, string>
     */
    public static function proofMimes(): array
    {
        $mimes = config('chat.proofs.mimes');

        return is_array($mimes) ? array_values($mimes) : ['jpg', 'jpeg', 'png', 'webp'];
    }

    public static function proofsDisk(): string
    {
        return self::string('chat.proofs.disk', 'public');
    }

    public static function proofsDirectory(): string
    {
        return self::string('chat.proofs.directory', 'chat-proofs');
    }

    /**
     * How many files one ordinary message may carry.
     *
     * A per-message cap rather than a total: several people trading screenshots
     * in one reply is the normal case here, while an unbounded thread is just
     * bulk file storage.
     */
    public static function maxAttachmentsPerMessage(): int
    {
        return (int) config('chat.attachments.max_per_message', 6);
    }

    public static function maxAttachmentSizeKb(): int
    {
        return (int) config('chat.attachments.max_size_kb');
    }

    /**
     * @return array<int, string>
     */
    public static function attachmentMimes(): array
    {
        $mimes = config('chat.attachments.mimes');

        return is_array($mimes) ? array_values($mimes) : ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
    }

    public static function attachmentsDisk(): string
    {
        return self::string('chat.attachments.disk', 'public');
    }

    public static function attachmentsDirectory(): string
    {
        return self::string('chat.attachments.directory', 'chat-attachments');
    }

    /**
     * The account name the admin's receiving account is registered under.
     *
     * Optional, and only a label beside the QRIS image the admin uploaded — the
     * image is what a payment app actually reads, so this never gates the
     * invoice action.
     */
    public static function qrisAccountName(): ?string
    {
        return self::nullableString('chat.qris.account_name');
    }

    public static function qrisAccountNumber(): ?string
    {
        return self::nullableString('chat.qris.account_number');
    }

    private static function string(string $key, string $fallback): string
    {
        $value = config($key);

        return is_string($value) && $value !== '' ? $value : $fallback;
    }

    private static function nullableString(string $key): ?string
    {
        $value = config($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
