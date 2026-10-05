import { useCallback, useEffect, useRef, type MouseEvent } from 'react';
import { cn } from '@/lib/utils';

export type ContextMenuState = {
    /** Viewport coordinates, from the right-click event. */
    x: number;
    y: number;
    /**
     * The element the menu was opened on.
     *
     * Carried rather than looked up: the caller needs to know *what* was
     * right-clicked to decide which items the menu should have, and the pointer
     * coordinates alone cannot answer that.
     */
    target: HTMLElement | null;
};

export type ContextMenuItem = {
    key: string;
    label: string;
    /** A short second line explaining what the action does. */
    hint?: string;
    tone?: 'default' | 'danger';
    onSelect: () => void;
};

/**
 * A menu for a right-click.
 *
 * Hand-built rather than pulled in, because the project ships no menu library and
 * the whole of this is: remember where the pointer was, draw a small list there,
 * and get out of the way on Escape, on a click elsewhere, or on a scroll.
 *
 * Two details are not optional and are the reason this is a component rather than
 * markup repeated in two places:
 *
 * - **Clamped to the viewport.** The pointer can be at the far right or bottom of
 * the screen, and a menu drawn at those coordinates opens off-screen with its
 * actions unreachable.
 * - **Dismissed on any outside interaction, including scroll.** A menu left open
 * while the list scrolls behind it has lost track of what it referred to.
 */
export function ContextMenu({
    state,
    items,
    onClose,
    label,
}: {
    state: ContextMenuState;
    items: ContextMenuItem[];
    onClose: () => void;
    label: string;
}) {
    const menuRef = useRef<HTMLDivElement | null>(null);

    useEffect(() => {
        function onDismiss(event: Event) {
            if (menuRef.current?.contains(event.target as Node)) {
                return;
            }

            onClose();
        }

        function onKeyDown(event: KeyboardEvent) {
            if (event.key === 'Escape') {
                onClose();
            }
        }

        // `pointerdown` rather than `click`: a right-click that opens the menu must
        // not be immediately closed by the same gesture's tail.
        window.addEventListener('pointerdown', onDismiss);
        window.addEventListener('keydown', onKeyDown);
        window.addEventListener('resize', onClose);
        window.addEventListener('scroll', onClose, true);

        return () => {
            window.removeEventListener('pointerdown', onDismiss);
            window.removeEventListener('keydown', onKeyDown);
            window.removeEventListener('resize', onClose);
            window.removeEventListener('scroll', onClose, true);
        };
    }, [onClose]);

    /*
     * Measured after mount, because the clamp needs the menu's own size and that is
     * only known once it is in the document. `left/top` are set from the pointer
     * during the render that opens it so the menu never appears at 0,0 first.
     */
    const width = 224;
    const estimatedHeight = items.length * 44 + 16;

    const left = Math.min(state.x, Math.max(8, window.innerWidth - width - 8));
    const top = Math.min(
        state.y,
        Math.max(8, window.innerHeight - estimatedHeight - 8),
    );

    return (
        <div
            ref={menuRef}
            role="menu"
            aria-label={label}
            style={{ left: `${left}px`, top: `${top}px`, width: `${width}px` }}
            className="fixed z-50 overflow-hidden rounded-lg border border-border-default bg-neutral-primary-soft py-1 shadow-lg"
        >
            {items.map((item) => (
                <button
                    key={item.key}
                    type="button"
                    role="menuitem"
                    onClick={() => {
                        onClose();
                        item.onSelect();
                    }}
                    className={cn(
                        'block w-full px-3 py-2 text-left transition-colors duration-100',
                        item.tone === 'danger'
                            ? 'text-fg-danger hover:bg-danger-soft'
                            : 'text-heading hover:bg-neutral-secondary-medium',
                    )}
                >
                    <span className="block text-[13px] font-medium">
                        {item.label}
                    </span>

                    {item.hint ? (
                        <span className="mt-0.5 block text-[11px] leading-4 text-body-subtle">
                            {item.hint}
                        </span>
                    ) : null}
                </button>
            ))}
        </div>
    );
}

/**
 * Opens `onOpen` with the pointer's viewport position, and suppresses the browser's
 * own menu.
 *
 * A hook rather than a prop so every right-clickable thing wires the same three
 * lines — capture the coordinates, call back, `preventDefault` — and none of them
 * can forget the `preventDefault` and end up with the browser menu on top of ours.
 */
/**
 * Opens `onOpen` with the pointer's viewport position, and suppresses the browser's
 * own menu.
 *
 * A hook rather than a prop so every right-clickable thing wires the same three
 * lines — capture the coordinates, call back, `preventDefault` — and none of them
 * can forget the `preventDefault` and end up with the browser menu on top of ours.
 *
 * Returned as a plain handler rather than an object so it can be spread onto an
 * element directly; passing the method out of an object is what makes a linter
 * (rightly) worry it could lose its `this`.
 */
export function useContextMenu(
    onOpen: (state: ContextMenuState) => void,
): (event: MouseEvent) => void {
    return useCallback(
        (event: MouseEvent) => {
            event.preventDefault();
            onOpen({
                x: event.clientX,
                y: event.clientY,
                target: event.currentTarget as HTMLElement | null,
            });
        },
        [onOpen],
    );
}
