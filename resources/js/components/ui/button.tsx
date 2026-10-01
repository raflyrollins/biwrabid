import type { ButtonHTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

export type ButtonVariant = 'brand' | 'white';

const base =
    'inline-flex items-center justify-center gap-2 px-6 py-4 text-base font-medium transition-colors duration-150 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:cursor-not-allowed disabled:bg-disabled disabled:text-fg-disabled';

const variants: Record<ButtonVariant, string> = {
    brand: 'bg-brand text-on-brand enabled:hover:bg-brand-strong dark:text-neutral-primary',
    white: 'bg-neutral-primary-soft text-fg-brand enabled:hover:bg-neutral-tertiary',
};

export function buttonClasses(
    variant: ButtonVariant = 'brand',
    className?: string,
): string {
    return cn(base, variants[variant], className);
}

type ButtonProps = ButtonHTMLAttributes<HTMLButtonElement> & {
    variant?: ButtonVariant;
};

export function Button({
    variant = 'brand',
    className,
    type = 'button',
    ...props
}: ButtonProps) {
    return (
        <button
            type={type}
            className={buttonClasses(variant, className)}
            {...props}
        />
    );
}
