import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type EmptyStateProps = {
    image: string;
    title: string;
    description?: string;
    action?: ReactNode;
    /**
     * The version that sits inside a panel rather than on a page.
     *
     * The page version is a bordered block with generous padding, which is right
     * when it is the thing being looked at and wrong inside the chat workspace:
     * there it would draw a box inside a box, push the illustration to a fifth of
     * the panel, and sit in the middle of a column the reader is trying to scan.
     * So this drops the frame and its padding, and caps the illustration instead —
     * an unDraw at its natural 960px would fill the whole half-screen and read as
     * a broken image rather than a drawing.
     */
    compact?: boolean;
};

export function EmptyState({
    image,
    title,
    description,
    action,
    compact = false,
}: EmptyStateProps) {
    return (
        <div
            className={cn(
                'flex flex-col items-center text-center',
                compact
                    ? 'px-6 py-8'
                    : 'border border-border-default bg-neutral-secondary-soft px-6 py-14',
            )}
        >
            <div
                className={cn(
                    'bg-white',
                    compact
                        ? 'w-full max-w-[12.5rem] p-3'
                        : 'w-full max-w-xs p-6',
                )}
            >
                <img
                    src={image}
                    alt=""
                    loading="lazy"
                    className="animate-illustration-in mx-auto w-full"
                />
            </div>

            <h2
                className={cn(
                    'font-heading font-bold text-heading',
                    compact ? 'mt-4 text-base' : 'mt-8 text-xl',
                )}
            >
                {title}
            </h2>

            {description ? (
                <p
                    className={cn(
                        'text-body-subtle',
                        compact ? 'mt-1.5 text-xs' : 'mt-2 max-w-md text-sm',
                    )}
                >
                    {description}
                </p>
            ) : null}

            {action ? (
                <div className={compact ? 'mt-4' : 'mt-6'}>{action}</div>
            ) : null}
        </div>
    );
}
