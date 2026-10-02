import { CheckCircleIcon, XCircleIcon } from '@/components/icons';
import { cn } from '@/lib/utils';
import { Modal } from '@/components/ui/modal';

export type ResultTone = 'success' | 'error';

type ResultDialogProps = {
    open: boolean;
    tone: ResultTone;
    title: string;
    message: string;
    confirmLabel: string;
    onClose: () => void;
};

/**
 * unDraw illustrations, recoloured to the brand palette in `COLORS.md`.
 *
 * These are served from `public/` rather than inlined so the SVGs stay cacheable
 * across every page that shows a result.
 */
const illustrations: Record<ResultTone, string> = {
    success: '/images/illustrations/action-success.svg',
    error: '/images/illustrations/action-error.svg',
};

const accents: Record<ResultTone, string> = {
    success: 'bg-success-soft text-fg-success-strong',
    error: 'bg-danger-soft text-fg-danger-strong',
};

/**
 * The outcome of an action the user just took: published, cancelled, closed early,
 * saved, and so on.
 *
 * This deliberately replaces the flash banner that used to sit above `main`.
 * A redirect plus a banner makes the result easy to scroll past, and on a long
 * page it sits off-screen entirely — which is how a seller ends up not noticing
 * that their auction was cancelled.
 */
export function ResultDialog({
    open,
    tone,
    title,
    message,
    confirmLabel,
    onClose,
}: ResultDialogProps) {
    const Icon = tone === 'success' ? CheckCircleIcon : XCircleIcon;

    return (
        <Modal
            open={open}
            onCancel={onClose}
            title={title}
            description={message}
            className="max-w-sm text-center"
            media={
                <div className="flex flex-col items-center">
                    <img
                        src={illustrations[tone]}
                        alt=""
                        width={240}
                        height={180}
                        className="animate-illustration-in h-auto w-full max-w-[220px]"
                    />
                    <span
                        className={cn(
                            '-mt-6 flex h-12 w-12 items-center justify-center rounded-full ring-4 ring-neutral-primary',
                            accents[tone],
                        )}
                        aria-hidden="true"
                    >
                        <Icon size={26} />
                    </span>
                </div>
            }
            footer={
                <button
                    type="button"
                    onClick={onClose}
                    data-modal-autofocus
                    className="w-full bg-brand px-4 py-2.5 text-sm font-medium text-on-brand transition-colors hover:bg-brand-strong dark:text-neutral-primary"
                >
                    {confirmLabel}
                </button>
            }
        />
    );
}
