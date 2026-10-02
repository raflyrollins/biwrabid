<?php

declare(strict_types=1);

namespace App\Support;

use NumberFormatter;

/**
 * Typed access to `config/auction.php`.
 *
 * Keeping the reads here means controllers, requests and presenters never
 * touch `config()` directly and never fall back to a hardcoded literal.
 *
 * @see RULES.md — "No hardcoding"
 */
final class AuctionConfig
{
    public static function minimumDurationHours(): int
    {
        return (int) config('auction.minimum_duration_hours');
    }

    public static function defaultDurationHours(): int
    {
        return (int) config('auction.default_duration_hours');
    }

    public static function maximumDurationHours(): int
    {
        return (int) config('auction.maximum_duration_hours');
    }

    public static function minimumStartingPrice(): int
    {
        return (int) config('auction.minimum_starting_price');
    }

    public static function minimumBidIncrement(): int
    {
        return (int) config('auction.minimum_bid_increment');
    }

    public static function bidPreviewCount(): int
    {
        return (int) config('auction.bids.preview_count');
    }

    /**
     * How recently a bid must have landed before an auction may be closed
     * early. Zero disables the anti-snipe guard.
     */
    public static function minimumMinutesSinceLastBid(): int
    {
        return (int) config('auction.minimum_minutes_since_last_bid');
    }

    public static function maxScreenshots(): int
    {
        return (int) config('auction.screenshots.max_count');
    }

    public static function maxScreenshotSizeKb(): int
    {
        return (int) config('auction.screenshots.max_size_kb');
    }

    public static function screenshotsDisk(): string
    {
        return self::string('auction.screenshots.disk', 'public');
    }

    public static function screenshotsDirectory(): string
    {
        return self::string('auction.screenshots.directory', 'auction-screenshots');
    }

    public static function currency(): string
    {
        return self::string('auction.currency', 'IDR');
    }

    public static function currencyLocale(): string
    {
        return self::string('auction.currency_locale', 'id');
    }

    /**
     * The flat admin fee the winner pays on top of the winning bid.
     *
     * Flat rather than a percentage so the buyer is quoted one number, and
     * zero means no fee at all rather than "no fee configured".
     */
    public static function adminFeeFlat(): int
    {
        return max(0, (int) config('auction.admin_fee_flat'));
    }

    /**
     * Format a whole-rupiah amount for display.
     *
     * Rupiah has no minor unit, but ICU formats `IDR` with two fraction digits
     * and renders them as a trailing `,00` - so "Rp 1.500.000,00". The fraction
     * digits are pinned to zero to match how the amounts are actually stored:
     * whole rupiah, no cents. `Number::currency()` takes no digit override, so
     * the formatter is configured directly.
     */
    public static function price(int $value): string
    {
        $formatter = new NumberFormatter(
            self::currencyLocale(),
            NumberFormatter::CURRENCY,
        );

        $formatter->setAttribute(NumberFormatter::FRACTION_DIGITS, 0);

        $formatted = $formatter->formatCurrency($value, self::currency());

        if ($formatted === false) {
            // `formatCurrency()` only reports false when ICU cannot build the
            // string at all. Display formatting runs on every listing card, so
            // it must never take a page down: fall back to the currency code
            // and a grouped integer, which keeps the same whole-rupiah shape.
            return sprintf(
                '%s %s',
                self::currency(),
                number_format($value, 0, '.', '.'),
            );
        }

        return $formatted;
    }

    private static function string(string $key, string $fallback): string
    {
        $value = config($key);

        return is_string($value) && $value !== '' ? $value : $fallback;
    }
}
