import { useEffect, useRef, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { Calendar } from '@/components/calendar';
import {
    clampDate,
    isSameDay,
    parseLocalDateTime,
    setTimeOfDay,
    startOfDay,
    startOfMonth,
} from '@/lib/calendar';
import { formatDateTime, toLocalInputValue } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';

type DateTimePickerProps = {
    id?: string;
    /** The stored value, as `YYYY-MM-DDTHH:mm` in local wall-clock time. */
    value: string;
    onValueChange: (value: string) => void;
    /** Inclusive lower/upper bounds, same format as `value`. */
    min: string;
    max: string;
    invalid?: boolean;
    placeholder?: string;
    className?: string;
};

const HOURS = Array.from({ length: 24 }, (_, hour) => hour);
const MINUTES = Array.from({ length: 60 }, (_, minute) => minute);

function pad(value: number): string {
    return String(value).padStart(2, '0');
}

/**
 * The earliest selectable moment on `day`.
 *
 * On the boundary day the bound's own time applies; on any other day the day
 * opens at midnight. Today never opens in the past, even when `min` is older.
 */
function lowerBoundOn(day: Date, min: Date | null, now: Date): Date {
    if (isSameDay(day, now)) {
        return min !== null && min > now ? min : now;
    }

    if (min !== null && isSameDay(day, min)) {
        return min;
    }

    return startOfDay(day);
}

/** The latest selectable moment on `day`, mirroring `lowerBoundOn`. */
function upperBoundOn(day: Date, max: Date | null): Date {
    if (max !== null && isSameDay(day, max)) {
        return max;
    }

    return new Date(
        day.getFullYear(),
        day.getMonth(),
        day.getDate(),
        23,
        59,
        0,
        0,
    );
}

/**
 * A date picker with an inline calendar and hour/minute selects.
 *
 * It replaces `datetime-local`, which renders a locale-styled native control
 * the design system has no say over, and it can express rules the native
 * control cannot — days outside the configured auction window are disabled, and
 * so are the individual hours and minutes that would land outside it.
 *
 * The value stays a local `YYYY-MM-DDTHH:mm` string so the caller owns the
 * conversion to UTC at submit time.
 */
export function DateTimePicker({
    id,
    value,
    onValueChange,
    min,
    max,
    invalid = false,
    placeholder,
    className,
}: DateTimePickerProps) {
    const { t } = useTranslation();
    const { locale } = usePage().props;

    const [open, setOpen] = useState(false);
    const [draft, setDraft] = useState<Date | null>(null);
    const [month, setMonth] = useState<Date>(startOfMonth(new Date()));

    const wrapperRef = useRef<HTMLDivElement>(null);
    const triggerRef = useRef<HTMLButtonElement>(null);
    const dialogRef = useRef<HTMLDivElement>(null);

    const minDate = parseLocalDateTime(min);
    const maxDate = parseLocalDateTime(max);
    const selected = parseLocalDateTime(value);

    // Seed the draft from the stored value each time the popover opens. The
    // trigger is a button rather than a text field, so nothing can change
    // `value` from the outside while the popover is open.
    useEffect(() => {
        if (!open) {
            return;
        }

        const seeded = parseLocalDateTime(value) ?? minDate;

        setDraft(seeded);
        setMonth(
            seeded === null ? startOfMonth(new Date()) : startOfMonth(seeded),
        );

        // Move focus into the dialog so a keyboard user lands on the calendar
        // instead of having to Tab back out to the trigger.
        dialogRef.current?.focus();
    }, [open]);

    useEffect(() => {
        if (!open) {
            return;
        }

        function onPointerDown(event: MouseEvent | TouchEvent) {
            if (!wrapperRef.current?.contains(event.target as Node)) {
                setOpen(false);
            }
        }

        function onKeyDown(event: KeyboardEvent) {
            if (event.key === 'Escape') {
                setOpen(false);
                triggerRef.current?.focus();
            }
        }

        document.addEventListener('mousedown', onPointerDown);
        document.addEventListener('touchstart', onPointerDown);
        document.addEventListener('keydown', onKeyDown);

        return () => {
            document.removeEventListener('mousedown', onPointerDown);
            document.removeEventListener('touchstart', onPointerDown);
            document.removeEventListener('keydown', onKeyDown);
        };
    }, [open]);

    function commit(next: Date) {
        const bounded = clampDate(next, minDate, maxDate);

        setDraft(bounded);
        onValueChange(toLocalInputValue(bounded));
    }

    function selectDay(day: Date) {
        const lower = lowerBoundOn(day, minDate, new Date());
        const upper = upperBoundOn(day, maxDate);
        const carried =
            draft === null
                ? new Date(day)
                : setTimeOfDay(day, draft.getHours(), draft.getMinutes());

        commit(clampDate(carried, lower, upper));
    }

    function selectTime(hour: number, minute: number) {
        if (draft === null) {
            return;
        }

        const lower = lowerBoundOn(draft, minDate, new Date());
        const upper = upperBoundOn(draft, maxDate);

        commit(clampDate(setTimeOfDay(draft, hour, minute), lower, upper));
    }

    function timeDisabled(hour: number, minute: number): boolean {
        if (draft === null) {
            return false;
        }

        const candidate = setTimeOfDay(draft, hour, minute);

        return (
            candidate < lowerBoundOn(draft, minDate, new Date()) ||
            candidate > upperBoundOn(draft, maxDate)
        );
    }

    const hour = draft?.getHours() ?? 0;
    const minute = draft?.getMinutes() ?? 0;

    return (
        <div ref={wrapperRef} className="relative">
            <button
                ref={triggerRef}
                id={id}
                type="button"
                onClick={() => setOpen((current) => !current)}
                aria-haspopup="dialog"
                aria-expanded={open}
                aria-invalid={invalid}
                className={cn(
                    'flex w-full items-center justify-between gap-3 border bg-neutral-secondary-medium px-3 py-2.5 text-left text-sm transition-all duration-200',
                    invalid
                        ? 'border-border-danger'
                        : 'border-border-default-medium hover:border-border-default-strong',
                    open && 'border-border-brand ring-1 ring-brand',
                    className,
                )}
            >
                <span className={value ? 'text-heading' : 'text-body'}>
                    {selected
                        ? formatDateTime(selected, locale)
                        : (placeholder ?? t('datetime.placeholder'))}
                </span>
                <span
                    aria-hidden="true"
                    className="shrink-0 text-xs text-body-subtle"
                >
                    {open ? t('datetime.close') : t('datetime.open_short')}
                </span>
            </button>

            {open ? (
                <div
                    ref={dialogRef}
                    role="dialog"
                    tabIndex={-1}
                    aria-label={t('datetime.dialog')}
                    className="absolute top-full left-0 z-50 mt-2 flex w-max flex-col gap-4 border border-border-default bg-neutral-primary-soft p-4 shadow-lg focus:outline-none"
                >
                    <Calendar
                        locale={locale}
                        month={month}
                        selected={draft}
                        min={minDate}
                        max={maxDate}
                        onMonthChange={setMonth}
                        onSelect={selectDay}
                    />

                    <div className="flex items-end gap-3 border-t border-border-default pt-4">
                        <div className="flex-1">
                            <label
                                htmlFor={`${id ?? 'datetime'}-hour`}
                                className="mb-1 block text-xs font-medium text-heading"
                            >
                                {t('datetime.hour')}
                            </label>
                            <select
                                id={`${id ?? 'datetime'}-hour`}
                                value={pad(hour)}
                                disabled={draft === null}
                                onChange={(event) =>
                                    selectTime(
                                        Number(event.target.value),
                                        minute,
                                    )
                                }
                                className="w-full border border-border-default bg-neutral-secondary-medium px-2 py-2 text-sm text-heading focus:border-border-brand focus:outline-none"
                            >
                                {HOURS.map((option) => (
                                    <option
                                        key={option}
                                        value={pad(option)}
                                        disabled={timeDisabled(option, minute)}
                                    >
                                        {pad(option)}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div className="flex-1">
                            <label
                                htmlFor={`${id ?? 'datetime'}-minute`}
                                className="mb-1 block text-xs font-medium text-heading"
                            >
                                {t('datetime.minute')}
                            </label>
                            <select
                                id={`${id ?? 'datetime'}-minute`}
                                value={pad(minute)}
                                disabled={draft === null}
                                onChange={(event) =>
                                    selectTime(hour, Number(event.target.value))
                                }
                                className="w-full border border-border-default bg-neutral-secondary-medium px-2 py-2 text-sm text-heading focus:border-border-brand focus:outline-none"
                            >
                                {MINUTES.map((option) => (
                                    <option
                                        key={option}
                                        value={pad(option)}
                                        disabled={timeDisabled(hour, option)}
                                    >
                                        {pad(option)}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <button
                            type="button"
                            onClick={() => {
                                setOpen(false);
                                triggerRef.current?.focus();
                            }}
                            className="border border-border-default bg-brand px-4 py-2 text-sm font-medium text-on-brand transition-colors hover:bg-brand-strong dark:text-neutral-primary"
                        >
                            {t('datetime.done')}
                        </button>
                    </div>
                </div>
            ) : null}
        </div>
    );
}
