export function formatDateTime(
    value: string | Date | null,
    locale: string,
): string {
    if (value === null || value === '') {
        return '';
    }

    const date = value instanceof Date ? value : new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return new Intl.DateTimeFormat(locale, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(date);
}

/**
 * Format an instant as a short "x minutes ago" label.
 *
 * Uses `Intl.RelativeTimeFormat` so the wording follows the active locale
 * instead of being assembled from English fragments.
 */
export function formatRelativeTime(
    value: string | Date | null,
    locale: string,
): string {
    if (value === null || value === '') {
        return '';
    }

    const date = value instanceof Date ? value : new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    const seconds = Math.round((date.getTime() - Date.now()) / 1000);
    const absolute = Math.abs(seconds);

    const units: Array<[Intl.RelativeTimeFormatUnit, number]> = [
        ['year', 60 * 60 * 24 * 365],
        ['month', 60 * 60 * 24 * 30],
        ['week', 60 * 60 * 24 * 7],
        ['day', 60 * 60 * 24],
        ['hour', 60 * 60],
        ['minute', 60],
    ];

    const formatter = new Intl.RelativeTimeFormat(locale, {
        numeric: 'auto',
    });

    for (const [unit, size] of units) {
        if (absolute >= size) {
            return formatter.format(Math.round(seconds / size), unit);
        }
    }

    return formatter.format(seconds, 'second');
}

/**
 * Group a digits-only string with the locale's thousands separators.
 *
 * The value stays a plain string so what is typed and what is stored never
 * drift apart: the grouped form is display only.
 */
export function formatThousands(
    value: string | number,
    locale: string,
): string {
    if (value === '') {
        return '';
    }

    try {
        return new Intl.NumberFormat(locale).format(BigInt(value));
    } catch {
        return String(value);
    }
}

/**
 * Format a whole-unit currency amount.
 *
 * Accepts both forms the amount arrives in: a digits-only string from
 * `<NumberInput>` while the seller is still typing, and a number from an Inertia
 * prop — an invoiced total, a winning bid — where the value is already a
 * server-side integer. `BigInt` is used so a large rupiah amount keeps its exact
 * digits rather than picking up float rounding at the display layer.
 *
 * The fraction digits are pinned on both ends so the live preview in the publish
 * form renders identically to `AuctionConfig::price()` on the server. Rupiah has
 * no minor unit, so a trailing `,00` would only ever be noise.
 */
export function formatCurrency(
    value: string | number,
    locale: string,
    currency: string,
): string {
    if (value === '' || value === null) {
        return '';
    }

    try {
        return new Intl.NumberFormat(locale, {
            style: 'currency',
            currency,
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).format(BigInt(value));
    } catch {
        return formatThousands(value, locale);
    }
}

/**
 * Format a date as the value a `datetime-local` input expects, in the
 * viewer's own timezone.
 */
export function toLocalInputValue(date: Date): string {
    const pad = (part: number) => String(part).padStart(2, '0');

    const day = `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(
        date.getDate(),
    )}`;

    const time = `${pad(date.getHours())}:${pad(date.getMinutes())}`;

    return `${day}T${time}`;
}
