import { Link, router, useForm, usePage } from '@inertiajs/react';
import { useEchoPublic } from '@laravel/echo-react';
import { useState, type FormEvent } from 'react';
import { NumberInput } from '@/components/number-input';
import { PaymentPanel } from '@/components/chat/payment-panel';
import { Badge } from '@/components/ui/badge';
import { buttonClasses } from '@/components/ui/button';
import { InputError } from '@/components/ui/input-error';
import { AppLayout } from '@/layouts/app-layout';
import { statusVariant } from '@/lib/auction';
import { formatDateTime } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';
import { useConfirm } from '@/hooks/use-confirm';
import { cn } from '@/lib/utils';
import { login } from '@/routes';
import {
    cancel as auctionsCancel,
    edit as auctionsEdit,
    index as auctionsIndex,
} from '@/routes/auctions';
import { store as storeBid } from '@/routes/auctions/bids';
import {
    credentials as chatCredentials,
    group as chatGroup,
} from '@/routes/chat/auction';
import type {
    AuctionDetail,
    AuctionEndedPayload,
    AuctionStatus,
    Bid,
    BidPlacedPayload,
    BiddingWindow,
    ChatRole,
} from '@/types/auction';
import type { AuctionPayment } from '@/types/chat';

type ShowProps = {
    auction: AuctionDetail;
    bids: Bid[];
    bidding: BiddingWindow;
    /**
     * The payment trail, for a viewer who is already in the group thread.
     *
     * Payment is coordinated in that thread, but it is auction state rather than
     * conversation, so the steps live here and the thread carries the receipt.
     * Null when the viewer has no group room yet — the chat button is what opens
     * one, and the panel appears on the next visit.
     */
    payment: AuctionPayment | null;
    can: {
        update: boolean;
        cancel: boolean;
        bid: boolean;
        /** The admin-visible thread: seller, winner and admin. */
        chat_group: boolean;
        chat_group_role: ChatRole | null;
        /** The private seller <-> winner handoff. The admin never sees this. */
        chat_credentials: boolean;
        chat_credentials_role: ChatRole | null;
    };
};

/**
 * The group thread is not a two-hander — the admin is in it too — so the button
 * names who is on the other end plus the admin. The credential handoff is the
 * seller and the winner alone, which is the point of keeping it separate.
 */
function groupLabel(role: ChatRole | null, t: (key: string) => string): string {
    return role === 'winner'
        ? t('chat.actions.open_group_chat_seller')
        : t('chat.actions.open_group_chat_winner');
}

function credentialsLabel(
    role: ChatRole | null,
    t: (key: string) => string,
): string {
    return role === 'winner'
        ? t('chat.actions.open_credentials_seller')
        : t('chat.actions.open_credentials_winner');
}

export default function AuctionShow({
    auction,
    bids: initialBids,
    bidding,
    payment,
    can,
}: ShowProps) {
    const { t } = useTranslation();
    const { locale, auth } = usePage().props;
    const [active, setActive] = useState(0);
    const { ask, dialog } = useConfirm();

    const [incoming, setIncoming] = useState<Bid[]>([]);
    const [livePrice, setLivePrice] = useState<string | null>(null);
    const [liveMinimum, setLiveMinimum] = useState<{
        value: number;
        label: string;
    } | null>(null);
    const [ended, setEnded] = useState<{ winner: string | null } | null>(null);

    const { data, setData, post, processing, errors } = useForm({
        amount: String(bidding.minimum_next_bid),
    });

    useEchoPublic<BidPlacedPayload>(
        `auctions.${auction.uuid}`,
        '.bid.placed',
        (payload) => {
            setIncoming((current) =>
                current.some((bid) => bid.id === payload.bid.id)
                    ? current
                    : [payload.bid, ...current],
            );
            setLivePrice(payload.current_price_label);
            setLiveMinimum({
                value: payload.minimum_next_bid,
                label: payload.minimum_next_bid_label,
            });
        },
        [auction.uuid],
    );

    useEchoPublic<AuctionEndedPayload>(
        `auctions.${auction.uuid}`,
        '.auction.ended',
        (payload) => {
            setEnded({ winner: payload.winner_name });
        },
        [auction.uuid],
    );

    const seen = new Set<number>();
    const merged: Bid[] = [];
    for (const bid of [...incoming, ...initialBids]) {
        if (seen.has(bid.id)) {
            continue;
        }
        seen.add(bid.id);
        merged.push(bid);
    }
    merged.sort((a, b) => b.id - a.id);
    const visibleBids = merged.slice(0, bidding.preview_count);

    const price = livePrice ?? auction.price;
    const minimum = liveMinimum ?? {
        value: bidding.minimum_next_bid,
        label: bidding.minimum_next_bid_label,
    };
    const belowMinimum = Number(data.amount || '0') < minimum.value;
    const isOpen = bidding.is_open && ended === null;
    const status: AuctionStatus = ended ? 'ended' : auction.status;
    const winnerName = ended?.winner ?? auction.winner_name;

    const screenshots = auction.screenshots;
    const current = screenshots[active] ?? screenshots[0] ?? null;

    function submitBid(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        post(storeBid.url(auction.uuid), { preserveScroll: true });
    }

    return (
        <AppLayout title={auction.title}>
            {dialog}
            <div className="mx-auto w-full max-w-[1152px] px-6 py-12">
                <Link
                    href={auctionsIndex.url()}
                    className="text-sm font-medium text-fg-brand underline underline-offset-4 hover:no-underline"
                >
                    {t('auctions.show.back')}
                </Link>

                <div className="mt-6 grid gap-10 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
                    <div className="min-w-0">
                        <div className="aspect-[16/10] w-full border border-border-default bg-neutral-secondary-medium">
                            {current ? (
                                <img
                                    src={current.url}
                                    alt={auction.title}
                                    className="h-full w-full object-contain"
                                />
                            ) : null}
                        </div>

                        {screenshots.length > 1 ? (
                            <div className="mt-4 grid grid-cols-4 gap-3 sm:grid-cols-6">
                                {screenshots.map((screenshot, index) => (
                                    <button
                                        key={screenshot.position}
                                        type="button"
                                        onClick={() => setActive(index)}
                                        className={cn(
                                            'aspect-square overflow-hidden border bg-neutral-secondary-medium',
                                            index === active
                                                ? 'border-border-brand'
                                                : 'border-border-default',
                                        )}
                                    >
                                        <img
                                            src={screenshot.url}
                                            alt=""
                                            className="h-full w-full object-cover"
                                        />
                                    </button>
                                ))}
                            </div>
                        ) : null}

                        <section className="mt-10">
                            <h2 className="font-heading text-xl font-bold text-heading">
                                {t('auctions.show.description')}
                            </h2>
                            <p className="mt-3 text-base leading-relaxed whitespace-pre-line text-body">
                                {auction.description}
                            </p>
                        </section>
                    </div>

                    <aside className="lg:sticky lg:top-6 lg:self-start">
                        <div className="border border-border-default bg-neutral-primary-soft p-6 shadow-xs">
                            <Badge variant={statusVariant(status)}>
                                {t(`auctions.status.${status}`)}
                            </Badge>
                            <h1 className="mt-4 font-heading text-2xl font-bold text-heading">
                                {auction.title}
                            </h1>
                            <p className="mt-2 text-sm text-body-subtle">
                                {t('auctions.show.seller')}:{' '}
                                {auction.seller_name}
                            </p>

                            <dl className="mt-6 space-y-4 border-t border-border-default pt-6">
                                <div>
                                    <dt className="text-xs font-medium tracking-wide text-body-subtle uppercase">
                                        {t('auctions.show.current_price')}
                                    </dt>
                                    <dd className="font-heading text-3xl font-bold text-fg-gold">
                                        {price}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-xs font-medium tracking-wide text-body-subtle uppercase">
                                        {t('auctions.show.starting_price')}
                                    </dt>
                                    <dd className="text-base font-medium text-heading">
                                        {auction.starting_price_label}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-xs font-medium tracking-wide text-body-subtle uppercase">
                                        {t('auctions.show.ends_at')}
                                    </dt>
                                    <dd className="text-base font-medium text-heading">
                                        {formatDateTime(
                                            auction.ends_at,
                                            locale,
                                        )}
                                    </dd>
                                </div>
                            </dl>

                            {winnerName ? (
                                <p className="mt-6 border border-border-success-subtle bg-success-soft px-3 py-2 text-sm font-medium text-fg-success-strong">
                                    {t('auctions.bids.winner', {
                                        name: winnerName,
                                    })}
                                </p>
                            ) : null}

                            {can.chat_group ? (
                                <Link
                                    href={chatGroup.url(auction.uuid)}
                                    method="post"
                                    as="button"
                                    className={buttonClasses(
                                        'brand',
                                        'mt-6 w-full px-5 py-2.5 text-sm',
                                    )}
                                >
                                    {groupLabel(can.chat_group_role, t)}
                                </Link>
                            ) : null}

                            {can.chat_credentials ? (
                                <Link
                                    href={chatCredentials.url(auction.uuid)}
                                    method="post"
                                    as="button"
                                    className={buttonClasses(
                                        'white',
                                        'mt-3 w-full border border-border-default px-5 py-2.5 text-sm',
                                    )}
                                >
                                    {credentialsLabel(
                                        can.chat_credentials_role,
                                        t,
                                    )}
                                </Link>
                            ) : null}

                            {can.chat_group && can.chat_credentials ? (
                                <p className="mt-3 text-xs text-body-subtle">
                                    {t('chat.actions.credentials_hint')}
                                </p>
                            ) : null}

                            {can.update || can.cancel ? (
                                <div className="mt-6 flex flex-wrap items-center gap-4 border-t border-border-default pt-6">
                                    {can.update ? (
                                        <Link
                                            href={auctionsEdit.url(
                                                auction.uuid,
                                            )}
                                            className={buttonClasses(
                                                'brand',
                                                'px-5 py-2.5 text-sm',
                                            )}
                                        >
                                            {t('auctions.show.edit')}
                                        </Link>
                                    ) : null}
                                    {can.cancel ? (
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
                                            {t('auctions.show.cancel')}
                                        </button>
                                    ) : null}
                                </div>
                            ) : null}
                        </div>

                        {payment ? (
                            <div className="mt-6">
                                <PaymentPanel
                                    roomUuid={payment.room_uuid}
                                    payment={payment.panel}
                                />
                            </div>
                        ) : null}

                        <div className="mt-6 border border-border-default bg-neutral-primary-soft p-6 shadow-xs">
                            <div className="flex items-center justify-between gap-3">
                                <h2 className="font-heading text-lg font-bold text-heading">
                                    {t('auctions.bids.title')}
                                </h2>
                                {isOpen ? (
                                    <span className="inline-flex items-center gap-1.5 text-xs font-medium text-fg-success-strong">
                                        <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-success" />
                                        {t('auctions.bids.live')}
                                    </span>
                                ) : null}
                            </div>

                            {isOpen ? (
                                auth.user ? (
                                    can.bid ? (
                                        <form
                                            onSubmit={submitBid}
                                            className="mt-5"
                                        >
                                            <label
                                                htmlFor="bid-amount"
                                                className="text-xs font-medium tracking-wide text-body-subtle uppercase"
                                            >
                                                {t('auctions.bids.amount')}
                                            </label>
                                            <div className="mt-2 flex gap-2">
                                                <NumberInput
                                                    id="bid-amount"
                                                    name="amount"
                                                    value={data.amount}
                                                    invalid={Boolean(
                                                        errors.amount,
                                                    )}
                                                    onValueChange={(value) =>
                                                        setData('amount', value)
                                                    }
                                                />
                                                <button
                                                    type="submit"
                                                    disabled={
                                                        processing ||
                                                        belowMinimum
                                                    }
                                                    className={buttonClasses(
                                                        'brand',
                                                        'shrink-0 px-4 py-2.5 text-sm',
                                                    )}
                                                >
                                                    {t('auctions.bids.place')}
                                                </button>
                                            </div>
                                            <div className="mt-2 flex items-center justify-between gap-3">
                                                <p className="text-xs text-body-subtle">
                                                    {t(
                                                        'auctions.bids.minimum',
                                                        {
                                                            amount: minimum.label,
                                                        },
                                                    )}
                                                </p>
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        setData(
                                                            'amount',
                                                            String(
                                                                minimum.value,
                                                            ),
                                                        )
                                                    }
                                                    className="text-xs font-medium text-fg-brand underline underline-offset-4 hover:no-underline"
                                                >
                                                    {t(
                                                        'auctions.bids.use_minimum',
                                                    )}
                                                </button>
                                            </div>
                                            <InputError
                                                message={errors.amount}
                                            />
                                        </form>
                                    ) : (
                                        <p className="mt-5 text-sm text-body-subtle">
                                            {t('auctions.bids.cannot_bid')}
                                        </p>
                                    )
                                ) : (
                                    <Link
                                        href={login.url()}
                                        className={buttonClasses(
                                            'brand',
                                            'mt-5 w-full px-4 py-2.5 text-center text-sm',
                                        )}
                                    >
                                        {t('auctions.bids.login')}
                                    </Link>
                                )
                            ) : (
                                <p className="mt-5 text-sm text-body-subtle">
                                    {t('auctions.bids.closed')}
                                </p>
                            )}

                            <ul className="mt-6 space-y-3 border-t border-border-default pt-5">
                                {visibleBids.length === 0 ? (
                                    <li className="text-sm text-body-subtle">
                                        {t('auctions.bids.empty')}
                                    </li>
                                ) : (
                                    visibleBids.map((bid, index) => (
                                        <li
                                            key={bid.id}
                                            className="flex items-center justify-between gap-3"
                                        >
                                            <span className="truncate text-sm text-body">
                                                {bid.bidder_name}
                                            </span>
                                            <span
                                                className={cn(
                                                    'font-heading text-sm font-bold',
                                                    index === 0
                                                        ? 'text-fg-gold'
                                                        : 'text-heading',
                                                )}
                                            >
                                                {bid.amount_label}
                                            </span>
                                        </li>
                                    ))
                                )}
                            </ul>
                        </div>
                    </aside>
                </div>
            </div>
        </AppLayout>
    );
}
