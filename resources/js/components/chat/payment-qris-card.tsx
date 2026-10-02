import { useTranslation } from '@/lib/i18n';

type Props = {
    qris: {
        image_url: string;
        account_name: string | null;
        account_number: string | null;
    };
};

/**
 * The receiving account the winner pays.
 *
 * Lives in its own component because it has to appear in two places that must
 * never disagree: the panel, while the admin is still deciding to send the
 * invoice, and the `payment_request` message in the transcript afterwards. A
 * buyer who scrolls back up has to be looking at the same account they were
 * originally shown.
 */
export function PaymentQrisCard({ qris }: Props) {
    const { t } = useTranslation();

    return (
        <div className="flex flex-col gap-4 border border-border-default bg-neutral-primary-soft p-4 sm:flex-row">
            <img
                src={qris.image_url}
                alt=""
                className="h-40 w-40 shrink-0 border border-border-default bg-white object-contain p-2"
            />
            <dl className="space-y-1 text-sm">
                <dt className="text-xs font-semibold tracking-wide text-body-subtle uppercase">
                    {t('chat.payment.qris_title')}
                </dt>
                {qris.account_name ? (
                    <div className="flex justify-between gap-4">
                        <dt className="text-body-subtle">
                            {t('chat.payment.account_name')}
                        </dt>
                        <dd className="text-heading">{qris.account_name}</dd>
                    </div>
                ) : null}
                {qris.account_number ? (
                    <div className="flex justify-between gap-4">
                        <dt className="text-body-subtle">
                            {t('chat.payment.account_number')}
                        </dt>
                        <dd className="text-heading">{qris.account_number}</dd>
                    </div>
                ) : null}
            </dl>
        </div>
    );
}
