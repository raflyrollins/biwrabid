import { usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import type { ResultTone } from '@/components/ui/result-dialog';
import { ResultDialog } from '@/components/ui/result-dialog';
import { useTranslation } from '@/lib/i18n';

type Result = {
    tone: ResultTone;
    message: string;
};

/**
 * Surfaces the `flash` props as a modal instead of a banner.
 *
 * A banner above `main` is easy to miss: on a long page the redirect that sets
 * it lands the user well below the fold, so a cancelled auction or a rejected
 * early close goes unseen. One instance is mounted in `AppLayout`, so every page
 * gets the same behaviour without any per-page wiring.
 *
 * The `seen` ref exists because a flash lives for the whole request it arrived
 * in, and Inertia re-evaluates shared props on partial reloads. Without it, any
 * `router.get(..., { only: [...] })` on the resulting page would pop the same
 * dialog open again. It resets once the flash clears, so performing the same
 * action twice still reports both outcomes.
 */
export function FlashResult() {
    const { t } = useTranslation();
    const { flash } = usePage().props;
    const [result, setResult] = useState<Result | null>(null);
    const seen = useRef<string | null>(null);

    const message = flash.error ?? flash.status;
    const tone: ResultTone = flash.error ? 'error' : 'success';

    useEffect(() => {
        if (message === null || message === '') {
            seen.current = null;

            return;
        }

        if (message === seen.current) {
            return;
        }

        seen.current = message;
        setResult({ message, tone });
    }, [message, tone]);

    if (result === null) {
        return null;
    }

    return (
        <ResultDialog
            open
            tone={result.tone}
            title={
                result.tone === 'success'
                    ? t('flash.success')
                    : t('flash.error')
            }
            message={result.message}
            confirmLabel={t('flash.dismiss')}
            onClose={() => setResult(null)}
        />
    );
}
