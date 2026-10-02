import type { HTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

export type BadgeVariant =
    | 'brand'
    | 'neutral'
    | 'gray'
    | 'danger'
    | 'success'
    | 'warning'
    | 'dark';

const variants: Record<BadgeVariant, string> = {
    brand: 'bg-brand-softer border-border-brand-subtle text-fg-brand-strong',
    neutral: 'bg-neutral-primary-soft border-border-default text-heading',
    gray: 'bg-neutral-secondary-medium border-border-default text-heading',
    danger: 'bg-danger-soft border-border-danger-subtle text-fg-danger-strong',
    success:
        'bg-success-soft border-border-success-subtle text-fg-success-strong',
    warning: 'bg-warning-soft border-border-warning-subtle text-fg-warning',
    dark: 'bg-dark border-transparent text-white',
};

type BadgeProps = HTMLAttributes<HTMLSpanElement> & {
    variant?: BadgeVariant;
};

export function Badge({ variant = 'neutral', className, ...props }: BadgeProps) {
    return (
        <span
            className={cn(
                'inline-flex items-center border px-1.5 py-0.5 text-xs font-medium',
                variants[variant],
                className,
            )}
            {...props}
        />
    );
}
