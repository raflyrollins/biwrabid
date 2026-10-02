import type { ReactNode } from 'react';
import { WarningIcon } from '@/components/icons';
import { cn } from '@/lib/utils';
import { Modal } from '@/components/ui/modal';

export type ConfirmTone = 'danger' | 'warning' | 'brand';

type ConfirmDialogProps = {
    open: boolean;
    title: string;
    description?: ReactNode;
    confirmLabel: string;
    cancelLabel: string;
    tone?: ConfirmTone;
    /** Shows the confirm button as busy and makes dismissal inert. */
    busy?: boolean;
    onConfirm: () => void;
    onCancel: () => void;
};

const tones: Record<ConfirmTone, string> = {
    danger: 'bg-danger-soft text-fg-danger-strong',
    warning: 'bg-warning-soft text-fg-warning',
    brand: 'bg-brand-softer text-fg-brand-strong',
};

/**
 * A confirmation dialog for actions that cannot be undone.
 *
 * All the modal behaviour — portal, focus trap, Escape and backdrop dismissal,
 * scroll lock, and the enter/exit animations — lives in `Modal`. This only
 * decides what the dialog looks like and what its buttons do.
 */
export function ConfirmDialog({
    open,
    title,
    description,
    confirmLabel,
    cancelLabel,
    tone = 'danger',
    busy = false,
    onConfirm,
    onCancel,
}: ConfirmDialogProps) {
    return (
        <Modal
            open={open}
            onCancel={onCancel}
            title={title}
            description={description}
            dismissible={!busy}
            media={
                <div
                    className={cn(
                        'flex h-10 w-10 items-center justify-center',
                        tones[tone],
                    )}
                    aria-hidden="true"
                >
                    <WarningIcon size={22} />
                </div>
            }
            footer={
                <>
                    <button
                        type="button"
                        onClick={onCancel}
                        disabled={busy}
                        className="border border-border-default px-4 py-2.5 text-sm font-medium text-heading transition-colors hover:bg-neutral-secondary-medium disabled:cursor-not-allowed disabled:text-fg-disabled"
                    >
                        {cancelLabel}
                    </button>
                    <button
                        type="button"
                        onClick={onConfirm}
                        disabled={busy}
                        data-modal-autofocus
                        className={cn(
                            'px-4 py-2.5 text-sm font-medium transition-colors disabled:cursor-not-allowed',
                            tone === 'danger'
                                ? 'bg-danger text-on-brand enabled:hover:bg-danger-strong dark:text-neutral-primary'
                                : 'bg-brand text-on-brand enabled:hover:bg-brand-strong dark:text-neutral-primary',
                            busy && 'opacity-70',
                        )}
                    >
                        {confirmLabel}
                    </button>
                </>
            }
        />
    );
}
