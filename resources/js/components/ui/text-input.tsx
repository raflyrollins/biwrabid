import type { InputHTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

type TextInputProps = InputHTMLAttributes<HTMLInputElement> & {
    invalid?: boolean;
};

export function TextInput({
    className,
    invalid = false,
    ...props
}: TextInputProps) {
    return (
        <input
            className={cn(
                'block w-full border bg-neutral-secondary-medium px-3 py-2.5 text-sm text-heading transition-all duration-200 placeholder:text-body focus:outline-none',
                invalid
                    ? 'border-border-danger focus:border-border-danger focus:ring-1 focus:ring-danger'
                    : 'border-border-default-medium hover:border-border-default-strong focus:border-border-brand focus:ring-1 focus:ring-brand',
                'disabled:cursor-not-allowed disabled:bg-disabled disabled:text-fg-disabled',
                className,
            )}
            aria-invalid={invalid}
            {...props}
        />
    );
}
