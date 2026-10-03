import { forwardRef, type ComponentPropsWithRef } from 'react';
import { cn } from '@/lib/utils';

type ScrollAreaProps = ComponentPropsWithRef<'div'>;

/**
 * A scrolling region with the app's slim scrollbar.
 *
 * The styling lives in `app.css` (`.scroll-slim`) rather than here: the two
 * engines need different properties and the Firefox half — `scrollbar-width` and
 * `scrollbar-color` — cannot be expressed as Tailwind utilities. The thumb also
 * needs a transparent border with `background-clip: padding-box` to become a
 * rounded pill, which is not something a utility class can carry either.
 *
 * This is a `div` and nothing more, so the ref the thread needs for
 * auto-scrolling is just the ref it would have put on its own scroll container.
 */
export const ScrollArea = forwardRef<HTMLDivElement, ScrollAreaProps>(
    ({ className, ...props }, ref) => {
        return (
            <div
                ref={ref}
                className={cn('scroll-slim', className)}
                {...props}
            />
        );
    },
);

ScrollArea.displayName = 'ScrollArea';
