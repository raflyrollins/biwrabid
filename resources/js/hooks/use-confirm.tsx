import { type ReactNode, useCallback, useState } from 'react';
import {
    ConfirmDialog,
    type ConfirmTone,
} from '@/components/ui/confirm-dialog';

export type ConfirmRequest = {
    title: string;
    description?: ReactNode;
    confirmLabel: string;
    cancelLabel: string;
    tone?: ConfirmTone;
    onConfirm: () => void;
};

/**
 * Holds one pending confirmation for a screen.
 *
 * Call sites do `onClick={() => ask({...})}` and render `dialog` once near the
 * root, so an action button stays a plain button instead of owning dialog
 * markup and open state.
 */
export function useConfirm() {
    const [request, setRequest] = useState<ConfirmRequest | null>(null);

    const ask = useCallback((next: ConfirmRequest) => setRequest(next), []);

    const close = useCallback(() => setRequest(null), []);

    const accept = useCallback(() => {
        request?.onConfirm();
        setRequest(null);
    }, [request]);

    const dialog =
        request === null ? null : (
            <ConfirmDialog
                open
                title={request.title}
                description={request.description}
                confirmLabel={request.confirmLabel}
                cancelLabel={request.cancelLabel}
                tone={request.tone ?? 'danger'}
                onConfirm={accept}
                onCancel={close}
            />
        );

    return { ask, dialog };
}
