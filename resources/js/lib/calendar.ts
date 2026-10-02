/**
 * Calendar helpers for the custom date picker.
 *
 * Everything here works on *wall-clock* time in the browser's timezone. The
 * auction end time is only converted to UTC when the form is submitted, so the
 * grid, the disabled days and the time bounds all have to agree with what the
 * seller actually sees. Using `new Date('2026-10-06T02:55')` would parse as UTC
 * and shift every cell, which is why `parseLocalDateTime` reads the parts and
 * builds the date locally instead.
 */

const LOCAL_DATE_TIME = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})/;

function pad(value: number): string {
    return String(value).padStart(2, '0');
}

/**
 * Read a `YYYY-MM-DDTHH:mm` string as local wall-clock time.
 */
export function parseLocalDateTime(value: string): Date | null {
    const match = LOCAL_DATE_TIME.exec(value);

    if (match === null) {
        return null;
    }

    const [, year, month, day, hour, minute] = match;

    const date = new Date(
        Number(year),
        Number(month) - 1,
        Number(day),
        Number(hour),
        Number(minute),
        0,
        0,
    );

    return Number.isNaN(date.getTime()) ? null : date;
}

export function toLocalDateKey(date: Date): string {
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

export function startOfDay(date: Date): Date {
    return new Date(date.getFullYear(), date.getMonth(), date.getDate());
}

export function endOfDay(date: Date): Date {
    return new Date(
        date.getFullYear(),
        date.getMonth(),
        date.getDate(),
        23,
        59,
        0,
        0,
    );
}

export function addDays(date: Date, days: number): Date {
    const next = new Date(date);
    next.setDate(next.getDate() + days);

    return next;
}

/** Always lands on the first of the target month, so `addMonths` never overflows. */
export function addMonths(date: Date, months: number): Date {
    return new Date(date.getFullYear(), date.getMonth() + months, 1);
}

export function startOfMonth(date: Date): Date {
    return new Date(date.getFullYear(), date.getMonth(), 1);
}

export function endOfMonth(date: Date): Date {
    return new Date(date.getFullYear(), date.getMonth() + 1, 0);
}

export function setTimeOfDay(day: Date, hour: number, minute: number): Date {
    return new Date(
        day.getFullYear(),
        day.getMonth(),
        day.getDate(),
        hour,
        minute,
        0,
        0,
    );
}

export function isSameDay(left: Date | null, right: Date | null): boolean {
    if (left === null || right === null) {
        return false;
    }

    return toLocalDateKey(left) === toLocalDateKey(right);
}

/**
 * The weekday the grid starts on, as a `Date#getDay()` index.
 *
 * Monday for every locale the app ships, which matches Indonesian calendar
 * convention. This is deliberately not read from `Intl.Locale.getWeekInfo()`:
 * the answer depends on which ICU data the host runtime bundles — small-icu
 * Node reports Sunday for `id` where Chrome reports Monday — so the grid would
 * silently shift between the browser, the SSR pass and the test runner.
 */
export function firstDayOfWeek(): number {
    return 1;
}

export function weekdayLabels(locale: string, firstDay: number): string[] {
    // 2024-01-01 was a Monday, so it stands in for `getDay() === 1`.
    const monday = new Date(2024, 0, 1);
    const formatter = new Intl.DateTimeFormat(locale, { weekday: 'short' });

    return Array.from({ length: 7 }, (_, index) =>
        formatter.format(addDays(monday, firstDay - 1 + index)),
    );
}

export function monthLabel(date: Date, locale: string): string {
    return new Intl.DateTimeFormat(locale, {
        month: 'long',
        year: 'numeric',
    }).format(date);
}

export function fullDateLabel(date: Date, locale: string): string {
    return new Intl.DateTimeFormat(locale, { dateStyle: 'full' }).format(date);
}

/**
 * A fixed six-week grid, so the calendar keeps the same height when paging from
 * a 28-day February to a 31-day month instead of reflowing under the cursor.
 */
export function monthGrid(month: Date, firstDay: number): Date[] {
    const leading = (startOfMonth(month).getDay() - firstDay + 7) % 7;
    const start = addDays(startOfMonth(month), -leading);

    return Array.from({ length: 42 }, (_, index) => addDays(start, index));
}

/** Pull a date into `[min, max]`; either bound may be `null` for unbounded. */
export function clampDate(
    date: Date,
    min: Date | null,
    max: Date | null,
): Date {
    if (min !== null && date < min) {
        return min;
    }

    if (max !== null && date > max) {
        return max;
    }

    return date;
}
