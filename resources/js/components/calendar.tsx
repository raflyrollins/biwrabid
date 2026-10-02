import {
    addMonths,
    endOfMonth,
    firstDayOfWeek,
    fullDateLabel,
    isSameDay,
    monthGrid,
    monthLabel,
    startOfDay,
    startOfMonth,
    weekdayLabels,
} from '@/lib/calendar';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';

type CalendarProps = {
    locale: string;
    /** Any date inside the month being shown. */
    month: Date;
    selected: Date | null;
    min: Date | null;
    max: Date | null;
    onMonthChange: (month: Date) => void;
    onSelect: (day: Date) => void;
};

/**
 * A month grid for picking a day.
 *
 * Days outside `[min, max]` are disabled rather than hidden, so the seller can
 * still see the shape of the month and understand *why* a date is unavailable.
 */
export function Calendar({
    locale,
    month,
    selected,
    min,
    max,
    onMonthChange,
    onSelect,
}: CalendarProps) {
    const { t } = useTranslation();

    const firstDay = firstDayOfWeek();
    const days = monthGrid(month, firstDay);
    const today = startOfDay(new Date());

    const previous = addMonths(month, -1);
    const next = addMonths(month, 1);

    // A month is unreachable once its last day precedes `min`, or its first
    // day follows `max` — paging into an all-disabled month is just a dead end.
    const previousBlocked =
        min !== null && endOfMonth(previous) < startOfDay(min);
    const nextBlocked = max !== null && startOfDay(next) > startOfDay(max);

    return (
        <div className="w-72">
            <div className="flex items-center justify-between">
                <button
                    type="button"
                    onClick={() => onMonthChange(previous)}
                    disabled={previousBlocked}
                    aria-label={t('datetime.previous_month')}
                    className="flex h-7 w-7 items-center justify-center text-lg text-fg-brand transition-colors hover:bg-neutral-secondary-medium disabled:cursor-not-allowed disabled:text-fg-disabled"
                >
                    ‹
                </button>

                <p
                    aria-live="polite"
                    className="font-heading text-sm font-bold text-heading"
                >
                    {monthLabel(startOfMonth(month), locale)}
                </p>

                <button
                    type="button"
                    onClick={() => onMonthChange(next)}
                    disabled={nextBlocked}
                    aria-label={t('datetime.next_month')}
                    className="flex h-7 w-7 items-center justify-center text-lg text-fg-brand transition-colors hover:bg-neutral-secondary-medium disabled:cursor-not-allowed disabled:text-fg-disabled"
                >
                    ›
                </button>
            </div>

            <div className="mt-4 grid grid-cols-7 gap-1">
                {weekdayLabels(locale, firstDay).map((label, index) => (
                    <span
                        key={`${label}-${index}`}
                        className="text-center text-xs font-medium tracking-wide text-body-subtle uppercase"
                    >
                        {label}
                    </span>
                ))}
            </div>

            <div className="mt-1 grid grid-cols-7 gap-1">
                {days.map((day) => {
                    const outside = day.getMonth() !== month.getMonth();
                    const disabled =
                        (min !== null && startOfDay(day) < startOfDay(min)) ||
                        (max !== null && startOfDay(day) > startOfDay(max));
                    const active = isSameDay(day, selected);
                    const isToday = isSameDay(day, today);

                    return (
                        <button
                            key={day.getTime()}
                            type="button"
                            disabled={disabled}
                            onClick={() => onSelect(day)}
                            aria-label={fullDateLabel(day, locale)}
                            aria-current={isToday ? 'date' : undefined}
                            aria-pressed={active}
                            className={cn(
                                'flex aspect-square w-full items-center justify-center border text-sm transition-colors',
                                disabled
                                    ? 'cursor-not-allowed text-fg-disabled'
                                    : active
                                      ? 'border-brand bg-brand font-semibold text-on-brand dark:text-neutral-primary'
                                      : isToday
                                        ? 'border-border-brand-subtle font-semibold text-fg-brand-strong hover:bg-neutral-secondary-medium'
                                        : 'border-transparent text-heading hover:bg-neutral-secondary-medium',
                                outside && !active && 'text-body-subtle',
                            )}
                        >
                            {day.getDate()}
                        </button>
                    );
                })}
            </div>
        </div>
    );
}
