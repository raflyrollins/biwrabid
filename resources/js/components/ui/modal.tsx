import type { ReactNode } from 'react';
import { useCallback, useEffect, useId, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { cn } from '@/lib/utils';

/**
 * How long the exit animation runs before the dialog unmounts. The dialogs
 * animate with keyframes rather than transitions, so this delay is what makes a
 * close visible instead of instantaneous. Keep it in sync with `dialog-out` and
 * `overlay-out` in app.css.
 */
const EXIT_MS = 150;

const FOCUSABLE =
    'a[href],button:not([disabled]),textarea:not([disabled]),input:not([disabled]),select:not([disabled]),[tabindex]:not([tabindex="-1"])';

type ModalProps = {
    open: boolean;
    onCancel: () => void;
    title: string;
    /** Announced with the title, so keep it short. */
    description?: ReactNode;
    /** Rendered above the title: an icon badge or an illustration. */
    media?: ReactNode;
    children?: ReactNode;
    footer?: ReactNode;
    /** Extra classes for the panel, e.g. a narrower or wider max width. */
    className?: string;
    /**
     * Whether Escape, a backdrop click and the cancel button close the dialog.
     * Set to `false` while a submit is in flight so a dialog cannot be
     * dismissed out from under a request.
     */
    dismissible?: boolean;
};

/**
 * The modal shell every dialog in the app is built on.
 *
 * It is portalled onto the document body so it escapes sticky form columns, and
 * it owns the three things a native `confirm()` gets for free but a real modal
 * has to do by hand: focus moves into the dialog and is trapped there, Escape
 * and a backdrop click cancel, and the page behind it does not scroll.
 *
 * Dismissal is two-staged on purpose. `closing` swaps the entrance keyframes
 * for the exit ones, and only once that has played does `onCancel` fire and let
 * the parent unmount the dialog. Unmounting on the click instead would drop the
 * exit animation entirely.
 */
export function Modal({
    open,
    onCancel,
    title,
    description,
    media,
    children,
    footer,
    className,
    dismissible = true,
}: ModalProps) {
    const panelRef = useRef<HTMLDivElement>(null);
    const restoreFocusRef = useRef<HTMLElement | null>(null);
    const [closing, setClosing] = useState(false);
    const titleId = useId();
    const descriptionId = useId();

    // Reopening after a close must replay the entrance animation.
    useEffect(() => {
        if (open) {
            setClosing(false);
        }
    }, [open]);

    const dismiss = useCallback(() => {
        if (!dismissible || closing) {
            return;
        }

        setClosing(true);
        window.setTimeout(onCancel, EXIT_MS);
    }, [closing, dismissible, onCancel]);

    // Remember what had focus so closing puts the caret back where the user
    // left it, instead of dropping them at the top of the document.
    useEffect(() => {
        if (!open) {
            return;
        }

        restoreFocusRef.current = document.activeElement as HTMLElement | null;

        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        // The panel is portalled in, so it has to wait one frame to exist
        // before the autofocus target can take focus.
        const frame = window.requestAnimationFrame(() => {
            const autofocus = panelRef.current?.querySelector<HTMLElement>(
                '[data-modal-autofocus]',
            );
            const fallback = panelRef.current?.querySelector<HTMLElement>(
                FOCUSABLE,
            );

            (autofocus ?? fallback)?.focus();
        });

        return () => {
            window.cancelAnimationFrame(frame);
            document.body.style.overflow = previousOverflow;
            restoreFocusRef.current?.focus();
        };
    }, [open]);

    // Tab cycles inside the dialog rather than walking into the page behind it.
    const trapFocus = useCallback((event: KeyboardEvent) => {
        if (event.key !== 'Tab' || panelRef.current === null) {
            return;
        }

        const focusable = Array.from(
            panelRef.current.querySelectorAll<HTMLElement>(FOCUSABLE),
        ).filter((element) => element.offsetParent !== null);

        if (focusable.length === 0) {
            return;
        }

        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }, []);

    useEffect(() => {
        if (!open) {
            return;
        }

        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                dismiss();
                return;
            }

            trapFocus(event);
        };

        document.addEventListener('keydown', onKeyDown);

        return () => document.removeEventListener('keydown', onKeyDown);
    }, [dismiss, open, trapFocus]);

    if (!open) {
        return null;
    }

    return createPortal(
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div
                className={cn(
                    'absolute inset-0 bg-dark/60',
                    closing ? 'animate-overlay-out' : 'animate-overlay-in',
                )}
                onClick={dismiss}
                aria-hidden="true"
            />
            <div
                ref={panelRef}
                role="dialog"
                aria-modal="true"
                aria-labelledby={titleId}
                aria-describedby={
                    description === undefined ? undefined : descriptionId
                }
                className={cn(
                    'relative w-full max-w-md border border-border-default bg-neutral-primary p-6 shadow-lg',
                    className,
                )}
            >
                {media !== undefined ? (
                    <div className="mb-5">{media}</div>
                ) : null}

                <h2
                    id={titleId}
                    className="font-heading text-lg font-bold text-heading"
                >
                    {title}
                </h2>

                {description !== undefined ? (
                    <div
                        id={descriptionId}
                        className="mt-2 space-y-2 text-sm text-body"
                    >
                        {description}
                    </div>
                ) : null}

                {children !== undefined ? (
                    <div className="mt-4">{children}</div>
                ) : null}

                {footer !== undefined ? (
                    <div className="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        {footer}
                    </div>
                ) : null}
            </div>
        </div>,
        document.body,
    );
}
