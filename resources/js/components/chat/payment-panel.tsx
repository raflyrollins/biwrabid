import { useRef } from 'react';
import { useForm, usePage } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { buttonClasses } from '@/components/ui/button';
import { InputError } from '@/components/ui/input-error';
import { useConfirm } from '@/hooks/use-confirm';
import { PaymentQrisCard } from '@/components/chat/payment-qris-card';
import { formatCurrency } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';
import { useFilePreviews } from '@/lib/use-file-previews';
import payment from '@/routes/chat/payment';
import type { PaymentPanel } from '@/types/chat';

type Props = {
    roomUuid: string;
    payment: PaymentPanel;
};

/** The steps that post a file rather than a bare confirmation. */
const uploadActions = ['request', 'proof', 'transfer'] as const;

type UploadAction = (typeof uploadActions)[number];

function isUploadAction(action: string | null): action is UploadAction {
    return action !== null && uploadActions.includes(action as UploadAction);
}

/**
 * The payment trail, rendered as a panel above the thread.
 *
 * Which button appears is `payment.actions`, which the server derived from the
 * viewer's role and the payment's current status. This component deliberately
 * does not re-derive any of it: a button that disagrees with its endpoint is a
 * button that lies, and the two would eventually drift.
 */
export function PaymentPanel({ roomUuid, payment: panel }: Props) {
    const { t } = useTranslation();
    const { locale, currency } = usePage().props;
    const { ask, dialog } = useConfirm();

    const action = panel.actions[0] ?? null;
    const upload = isUploadAction(action);

    const form = useForm<{ qris: File | null; proof: File | null }>({
        qris: null,
        proof: null,
    });
    const inputRef = useRef<HTMLInputElement>(null);

    /*
     * Two named fields rather than one, because the endpoints disagree about
     * what they accept: the invoice wants a `qris`, the two receipt steps want a
     * `proof`. Sending the file under both names would mean every receipt
     * uploaded two files.
     */
    const field: 'qris' | 'proof' = action === 'request' ? 'qris' : 'proof';
    const previews = useFilePreviews(
        form.data[field] ? [form.data[field]] : [],
    );

    function submitUpload(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (form.data[field] === null || form.processing) {
            return;
        }

        form.post(
            action === 'request'
                ? payment.request.url(roomUuid)
                : action === 'transfer'
                  ? payment.transfer.url(roomUuid)
                  : payment.proof.url(roomUuid),
            {
                forceFormData: true,
                onSuccess: () => {
                    form.setData(field, null);
                    clearFile();
                },
            },
        );
    }

    function clearFile() {
        form.setData(field, null);

        if (inputRef.current) {
            inputRef.current.value = '';
        }
    }

    /**
     * The three money-moving steps ask first. `tone` is `brand` rather than the
     * default `danger` because none of them deletes anything — they advance an
     * irreversible payment trail, which deserves a pause without a threat.
     */
    function confirmStep(url: string) {
        if (action === null) {
            return;
        }

        ask({
            title: t(`chat.payment.actions.${action}`),
            description: t(`chat.payment.hints.${action}`),
            confirmLabel: t('chat.payment.dialog.confirm'),
            cancelLabel: t('chat.payment.dialog.cancel'),
            tone: 'brand',
            onConfirm: () => form.post(url),
        });
    }

    return (
        <section className="border border-border-default bg-neutral-secondary-soft px-5 py-4">
            {dialog}

            <header className="flex flex-wrap items-center justify-between gap-2">
                <h2 className="font-heading text-sm font-bold text-heading">
                    {t('chat.payment.heading')}
                </h2>

                {panel.status ? (
                    <Badge
                        variant={
                            panel.status === 'completed' ? 'success' : 'warning'
                        }
                    >
                        {t(`chat.payment.steps.${panel.status}`)}
                    </Badge>
                ) : null}
            </header>

            <p className="mt-2 text-xs text-body-subtle">
                {t('chat.payment.intro')}
            </p>

            <dl className="mt-4 space-y-1.5 text-sm">
                {panel.bid_amount !== null ? (
                    <div className="flex justify-between gap-4">
                        <dt className="text-body-subtle">
                            {t('chat.payment.bid_label')}
                        </dt>
                        <dd className="text-heading">
                            {formatCurrency(panel.bid_amount, locale, currency)}
                        </dd>
                    </div>
                ) : null}

                {panel.admin_fee > 0 ? (
                    <div className="flex justify-between gap-4">
                        <dt className="text-body-subtle">
                            {t('chat.payment.fee_label')}
                        </dt>
                        <dd className="text-heading">
                            {formatCurrency(panel.admin_fee, locale, currency)}
                        </dd>
                    </div>
                ) : null}

                <div className="flex justify-between gap-4 border-t border-border-default pt-1.5">
                    <dt className="font-medium text-heading">
                        {t('chat.payment.total_label')}
                    </dt>
                    <dd className="font-heading font-bold text-heading">
                        {formatCurrency(panel.amount ?? 0, locale, currency)}
                    </dd>
                </div>
            </dl>

            {panel.qris && action === 'request' ? (
                <div className="mt-4">
                    <PaymentQrisCard qris={panel.qris} />
                </div>
            ) : null}

            {panel.status === 'requested' &&
            !panel.has_proof &&
            action !== 'proof' ? (
                <p className="mt-3 text-xs text-fg-warning">
                    {t('chat.payment.no_proof_yet')}
                </p>
            ) : null}

            <div className="mt-4">
                {upload ? (
                    <form onSubmit={submitUpload} className="space-y-2">
                        <input
                            ref={inputRef}
                            type="file"
                            accept="image/*"
                            className="sr-only"
                            onChange={(event) =>
                                form.setData(
                                    field,
                                    event.target.files?.[0] ?? null,
                                )
                            }
                        />

                        {previews[0] ? (
                            <img
                                src={previews[0]}
                                alt=""
                                className="max-h-48 w-full border border-border-default object-contain"
                            />
                        ) : (
                            <button
                                type="button"
                                onClick={() => inputRef.current?.click()}
                                className={buttonClasses(
                                    'white',
                                    'w-full border border-border-default px-4 py-2.5 text-sm',
                                )}
                            >
                                {field === 'qris'
                                    ? t('chat.payment.choose_qris')
                                    : t('chat.payment.choose_proof')}
                            </button>
                        )}

                        <InputError message={form.errors[field]} />

                        <div className="flex gap-2">
                            <button
                                type="submit"
                                disabled={
                                    form.data[field] === null || form.processing
                                }
                                className={buttonClasses(
                                    'brand',
                                    'flex-1 px-4 py-2.5 text-sm',
                                )}
                            >
                                {form.processing
                                    ? t('chat.payment.uploading')
                                    : t(`chat.payment.actions.${action}`)}
                            </button>

                            {previews[0] ? (
                                <button
                                    type="button"
                                    onClick={clearFile}
                                    className={buttonClasses(
                                        'white',
                                        'border border-border-default px-4 py-2.5 text-sm',
                                    )}
                                >
                                    {t('chat.payment.dialog.cancel')}
                                </button>
                            ) : null}
                        </div>

                        <p className="text-xs text-body-subtle">
                            {t(`chat.payment.hints.${action}`)}
                        </p>
                    </form>
                ) : null}

                {action !== null && !upload ? (
                    <>
                        <button
                            type="button"
                            disabled={form.processing}
                            onClick={() =>
                                confirmStep(
                                    action === 'received'
                                        ? payment.received.url(roomUuid)
                                        : payment.confirm.url(roomUuid),
                                )
                            }
                            className={buttonClasses(
                                'brand',
                                'w-full px-4 py-2.5 text-sm',
                            )}
                        >
                            {t(`chat.payment.actions.${action}`)}
                        </button>
                        <p className="mt-2 text-xs text-body-subtle">
                            {t(`chat.payment.hints.${action}`)}
                        </p>
                    </>
                ) : null}

                {action === null ? (
                    <p className="text-sm text-body-subtle">
                        {t('chat.payment.intro')}
                    </p>
                ) : null}
            </div>
        </section>
    );
}
