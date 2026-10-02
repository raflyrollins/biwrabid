import { Link, router, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button, buttonClasses } from '@/components/ui/button';
import { Pagination } from '@/components/ui/pagination';
import { TextInput } from '@/components/ui/text-input';
import { AppLayout } from '@/layouts/app-layout';
import { statusVariant } from '@/lib/auction';
import { formatDateTime } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';
import { register } from '@/routes';
import {
    create as auctionsCreate,
    index as auctionsIndex,
    show as auctionsShow,
} from '@/routes/auctions';
import type { AuctionSummary, Paginated } from '@/types/auction';

type IndexProps = {
    auctions: Paginated<AuctionSummary>;
    filters: { q: string | null };
};

export default function AuctionsIndex({ auctions, filters }: IndexProps) {
    const { t } = useTranslation();
    const { locale, auth } = usePage().props;
    const [term, setTerm] = useState(filters.q ?? '');

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        router.get(auctionsIndex.url(), term ? { q: term } : {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    return (
        <AppLayout title={t('auctions.index.title')}>
            <section data-section="storefront-hero" className="bg-brand">
                <div className="mx-auto w-full max-w-[1152px] px-6 py-16">
                    <h1 className="font-heading text-3xl font-bold text-white md:text-4xl dark:text-neutral-primary">
                        {t('auctions.index.headline')}
                    </h1>
                    <p className="mt-4 max-w-2xl text-lg text-on-brand-muted">
                        {t('auctions.index.subtitle')}
                    </p>
                    <form
                        onSubmit={submit}
                        className="mt-8 flex max-w-xl items-stretch gap-3"
                    >
                        <TextInput
                            type="search"
                            name="q"
                            value={term}
                            onChange={(event) => setTerm(event.target.value)}
                            placeholder={t('auctions.index.search_placeholder')}
                            aria-label={t('auctions.index.search_placeholder')}
                        />
                        <Button type="submit">
                            {t('auctions.index.search')}
                        </Button>
                    </form>
                </div>
            </section>

            <section className="mx-auto w-full max-w-[1152px] px-6 py-16">
                {auctions.data.length === 0 ? (
                    <EmptyState
                        image={
                            filters.q
                                ? '/images/illustrations/searching.svg'
                                : '/images/illustrations/mobile-gaming.svg'
                        }
                        title={
                            filters.q
                                ? t('auctions.index.empty_search_title')
                                : t('auctions.index.empty_title')
                        }
                        description={
                            filters.q
                                ? t('auctions.index.empty_search_body')
                                : t('auctions.index.empty_body')
                        }
                        action={
                            <Link
                                href={
                                    filters.q
                                        ? auctionsIndex.url()
                                        : auth.user
                                          ? auctionsCreate.url()
                                          : register.url()
                                }
                                className={buttonClasses(
                                    'brand',
                                    'px-5 py-2.5 text-sm',
                                )}
                            >
                                {filters.q
                                    ? t('auctions.index.clear_search')
                                    : auth.user
                                      ? t('auctions.my.create')
                                      : t('welcome.register')}
                            </Link>
                        }
                    />
                ) : (
                    <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        {auctions.data.map((auction) => (
                            <Link
                                key={auction.uuid}
                                href={auctionsShow.url(auction.uuid)}
                                className="group flex flex-col border border-border-default bg-neutral-primary-soft shadow-xs transition-colors hover:bg-neutral-secondary-medium"
                            >
                                <div className="aspect-[16/10] w-full overflow-hidden bg-neutral-secondary-medium">
                                    {auction.screenshot ? (
                                        <img
                                            src={auction.screenshot}
                                            alt={auction.title}
                                            loading="lazy"
                                            className="h-full w-full object-cover"
                                        />
                                    ) : null}
                                </div>
                                <div className="flex flex-1 flex-col gap-3 p-5">
                                    <Badge
                                        variant={statusVariant(auction.status)}
                                    >
                                        {t(`auctions.status.${auction.status}`)}
                                    </Badge>
                                    <h2 className="font-heading text-lg font-bold text-heading">
                                        {auction.title}
                                    </h2>
                                    <div className="mt-auto">
                                        <p className="text-xs font-medium tracking-wide text-body-subtle uppercase">
                                            {t(
                                                auction.current_price === null
                                                    ? 'auctions.index.starting_price'
                                                    : 'auctions.index.current_price',
                                            )}
                                        </p>
                                        <p className="font-heading text-2xl font-bold text-fg-gold">
                                            {auction.price}
                                        </p>
                                    </div>
                                    <p className="text-xs text-body-subtle">
                                        {t('auctions.index.ends_at')}:{' '}
                                        {formatDateTime(
                                            auction.ends_at,
                                            locale,
                                        )}
                                    </p>
                                </div>
                            </Link>
                        ))}
                    </div>
                )}

                <div className="mt-10">
                    <Pagination links={auctions.links} />
                </div>
            </section>
        </AppLayout>
    );
}
