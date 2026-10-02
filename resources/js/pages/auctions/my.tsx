import { Link, router, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { PencilIcon, TrashIcon } from '@/components/icons';
import { DateTimePicker } from '@/components/date-time-picker';
import { EmptyState } from '@/components/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button, buttonClasses } from '@/components/ui/button';
import { Pagination } from '@/components/ui/pagination';
import { AppLayout } from '@/layouts/app-layout';
import { statusVariant } from '@/lib/auction';
import { formatDateTime, toLocalInputValue } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';
import { useConfirm } from '@/hooks/use-confirm';
import {
    cancel as auctionsCancel,
    create as auctionsCreate,
    destroy as auctionsDestroy,
    edit as auctionsEdit,
    publish as auctionsPublish,
    show as auctionsShow,
    endEarly as auctionsEndEarly,
} from '@/routes/auctions';
import type { AuctionSummary, Paginated } from '@/types/auction';

type MyAuctionsProps = {
    auctions: Paginated<
        AuctionSummary & {
            can_end_early: boolean;
            end_early_blocked_reason: string | null;
            highest_bidder_name: string | null;
        }
    >;
    minimumDurationHours: number;
    defaultDurationHours: number;
    maximumDurationHours: number;
};

export default function MyAuctions({
    auctions,
    minimumDurationHours,
    defaultDurationHours,
    maximumDurationHours,
}: MyAuctionsProps) {
    const { t } = useTranslation();
    const { locale } = usePage().props;
    const { ask, dialog } = useConfirm();

    return (
        <AppLayout title={t('auctions.my.title')}>
            {dialog}
            <div className="mx-auto w-full max-w-[1152px] px-6 py-12">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="font-heading text-2xl font-bold text-heading">
                            {t('auctions.my.title')}
                        </h1>
                        <p className="mt-2 text-sm text-body-subtle">
                            {t('auctions.my.subtitle')}
                        </p>
                    </div>
                    <Link
                        href={auctionsCreate.url()}
                        className={buttonClasses(
                            'brand',
                            'px-5 py-2.5 text-sm',
                        )}
                    >
                        {t('auctions.my.create')}
                    </Link>
                </div>

                {auctions.data.length === 0 ? (
                    <div className="mt-8">
                        <EmptyState
                            image="/images/illustrations/mobile-gaming.svg"
                            title={t('auctions.my.empty_title')}
                            description={t('auctions.my.empty_body')}
                            action={
                                <Link
                                    href={auctionsCreate.url()}
                                    className={buttonClasses(
                                        'brand',
                                        'px-5 py-2.5 text-sm',
                                    )}
                                >
                                    {t('auctions.my.create')}
                                </Link>
                            }
                        />
                    </div>
                ) : (
                    <div className="mt-8 space-y-4">
                        {auctions.data.map((auction) => (
                            <article
                                key={auction.uuid}
                                className="border border-border-default bg-neutral-primary-soft shadow-xs"
                            >
                                <div className="flex flex-col gap-4 p-5 md:flex-row md:items-start md:justify-between">
                                    <div className="flex items-center gap-4">
                                        <div className="h-16 w-24 shrink-0 overflow-hidden bg-neutral-secondary-medium">
                                            {auction.screenshot ? (
                                                <img
                                                    src={auction.screenshot}
                                                    alt=""
                                                    className="h-full w-full object-cover"
                                                />
                                            ) : null}
                                        </div>
                                        <div>
                                            <div className="flex flex-wrap items-center gap-3">
                                                <Badge
                                                    variant={statusVariant(
                                                        auction.status,
                                                    )}
                                                >
                                                    {t(
                                                        `auctions.status.${auction.status}`,
                                                    )}
                                                </Badge>
                                                <Link
                                                    href={auctionsShow.url(
                                                        auction.uuid,
                                                    )}
                                                    className="font-heading text-base font-bold text-heading hover:text-fg-brand"
                                                >
                                                    {auction.title}
                                                </Link>
                                            </div>
                                            <p className="mt-1 text-sm text-body-subtle">
                                                {t('auctions.my.price')}:{' '}
                                                <span className="font-medium text-fg-gold">
                                                    {auction.price}
                                                </span>
                                                {auction.ends_at
                                                    ? ` · ${t('auctions.my.ends_at')}: ${formatDateTime(auction.ends_at, locale)}`
                                                    : null}
                                            </p>
                                        </div>
                                    </div>

                                    <div className="flex flex-wrap items-center gap-4">
                                        {auction.status === 'draft' ? (
                                            <>
                                                <PublishForm
                                                    auction={auction}
                                                    defaultHours={
                                                        defaultDurationHours
                                                    }
                                                    min={minimumDurationHours}
                                                    max={maximumDurationHours}
                                                />
                                                <div className="flex shrink-0 items-center gap-1 self-center">
                                                    <Link
                                                        href={auctionsEdit.url(
                                                            auction.uuid,
                                                        )}
                                                        title={t(
                                                            'auctions.my.edit',
                                                        )}
                                                        aria-label={t(
                                                            'auctions.my.edit',
                                                        )}
                                                        className="flex h-10 w-10 items-center justify-center border border-border-default text-fg-brand transition-colors hover:bg-neutral-secondary-medium"
                                                    >
                                                        <PencilIcon />
                                                    </Link>
                                                    <button
                                                        type="button"
                                                        title={t(
                                                            'auctions.my.delete',
                                                        )}
                                                        aria-label={t(
                                                            'auctions.my.delete',
                                                        )}
                                                        onClick={() =>
                                                            ask({
                                                                title: t(
                                                                    'auctions.confirm.delete.title',
                                                                ),
                                                                description: t(
                                                                    'auctions.confirm.delete.body',
                                                                ),
                                                                confirmLabel: t(
                                                                    'auctions.confirm.delete.confirm',
                                                                ),
                                                                cancelLabel: t(
                                                                    'auctions.confirm.cancel_button',
                                                                ),
                                                                onConfirm: () =>
                                                                    router.delete(
                                                                        auctionsDestroy.url(
                                                                            auction.uuid,
                                                                        ),
                                                                    ),
                                                            })
                                                        }
                                                        className="flex h-10 w-10 items-center justify-center border border-border-default text-fg-danger transition-colors hover:bg-neutral-secondary-medium"
                                                    >
                                                        <TrashIcon />
                                                    </button>
                                                </div>
                                            </>
                                        ) : null}
                                        {auction.status === 'active' ? (
                                            <>
                                                {auction.highest_bidder_name !==
                                                null ? (
                                                    <button
                                                        type="button"
                                                        disabled={
                                                            !auction.can_end_early
                                                        }
                                                        title={
                                                            auction.end_early_blocked_reason ??
                                                            undefined
                                                        }
                                                        onClick={() =>
                                                            ask({
                                                                title: t(
                                                                    'auctions.confirm.end_early.title',
                                                                ),
                                                                description:
                                                                    auction.highest_bidder_name !==
                                                                    null
                                                                        ? t(
                                                                              'auctions.early_close.hint_with_bids',
                                                                              {
                                                                                  name: auction.highest_bidder_name,
                                                                                  price:
                                                                                      auction.current_price_label ??
                                                                                      '',
                                                                              },
                                                                          )
                                                                        : t(
                                                                              'auctions.early_close.hint_no_bids',
                                                                          ),
                                                                confirmLabel: t(
                                                                    'auctions.confirm.end_early.confirm',
                                                                ),
                                                                cancelLabel: t(
                                                                    'auctions.confirm.cancel_button',
                                                                ),
                                                                tone: 'warning',
                                                                onConfirm: () =>
                                                                    router.post(
                                                                        auctionsEndEarly.url(
                                                                            auction.uuid,
                                                                        ),
                                                                    ),
                                                            })
                                                        }
                                                        className="text-sm font-medium text-heading underline underline-offset-4 hover:no-underline disabled:cursor-not-allowed disabled:text-body-subtle disabled:no-underline"
                                                    >
                                                        {t(
                                                            'auctions.early_close.label',
                                                        )}
                                                    </button>
                                                ) : null}
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        ask({
                                                            title: t(
                                                                'auctions.confirm.cancel.title',
                                                            ),
                                                            description: t(
                                                                'auctions.confirm.cancel.body',
                                                            ),
                                                            confirmLabel: t(
                                                                'auctions.confirm.cancel.confirm',
                                                            ),
                                                            cancelLabel: t(
                                                                'auctions.confirm.cancel_button',
                                                            ),
                                                            onConfirm: () =>
                                                                router.post(
                                                                    auctionsCancel.url(
                                                                        auction.uuid,
                                                                    ),
                                                                ),
                                                        })
                                                    }
                                                    className="text-sm font-medium text-fg-danger underline underline-offset-4 hover:no-underline"
                                                >
                                                    {t('auctions.my.cancel')}
                                                </button>
                                            </>
                                        ) : null}
                                    </div>
                                </div>
                            </article>
                        ))}
                    </div>
                )}

                <div className="mt-10">
                    <Pagination links={auctions.links} />
                </div>
            </div>
        </AppLayout>
    );
}

type PublishFormProps = {
    auction: AuctionSummary;
    defaultHours: number;
    min: number;
    max: number;
};

function hoursFromNow(hours: number): Date {
    return new Date(Date.now() + hours * 60 * 60 * 1000);
}

function PublishForm({ auction, defaultHours, min, max }: PublishFormProps) {
    const { t } = useTranslation();
    const [endsAt, setEndsAt] = useState(() =>
        toLocalInputValue(hoursFromNow(defaultHours)),
    );
    const [submitting, setSubmitting] = useState(false);
    const { ask, dialog } = useConfirm();

    function publish() {
        // Publishing flips the draft to active, so a second POST for the same
        // auction is refused by the policy. Without this guard a double-click
        // fires two visits and the loser renders a bare 403 page.
        if (submitting) {
            return;
        }

        setSubmitting(true);

        router.post(
            auctionsPublish.url(auction.uuid),
            { ends_at: endsAt ? new Date(endsAt).toISOString() : '' },
            {
                preserveScroll: true,
                onFinish: () => setSubmitting(false),
            },
        );
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        ask({
            title: t('auctions.confirm.publish.title'),
            description: t('auctions.confirm.publish.body'),
            confirmLabel: t('auctions.confirm.publish.confirm'),
            cancelLabel: t('auctions.confirm.cancel_button'),
            tone: 'brand',
            onConfirm: publish,
        });
    }

    return (
        <>
            {dialog}
            {/* Column, not a flex row: the label and the hint stack above and
                below the field so neither can push the publish button off the
                field's baseline. */}
            <form onSubmit={submit} className="flex flex-col gap-1">
                <label
                    htmlFor={`ends_at-${auction.uuid}`}
                    className="text-xs font-medium text-heading"
                >
                    {t('auctions.my.publish_ends_at')}
                </label>

                <div className="flex items-center gap-2">
                    <div className="w-56">
                        <DateTimePicker
                            id={`ends_at-${auction.uuid}`}
                            className="h-11"
                            value={endsAt}
                            min={toLocalInputValue(hoursFromNow(min))}
                            max={toLocalInputValue(hoursFromNow(max))}
                            onValueChange={setEndsAt}
                        />
                    </div>
                    <Button
                        type="submit"
                        disabled={submitting}
                        className="h-11 px-4 py-0 text-sm"
                    >
                        {t('auctions.my.publish')}
                    </Button>
                </div>

                <p className="text-xs text-body-subtle">
                    {t('auctions.my.publish_hint', { min, max })}
                </p>
            </form>
        </>
    );
}
