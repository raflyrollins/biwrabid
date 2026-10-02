import { Link, usePage } from '@inertiajs/react';
import { buttonClasses } from '@/components/ui/button';
import { AppLayout } from '@/layouts/app-layout';
import { heroAssets } from '@/lib/heroes';
import { useTranslation } from '@/lib/i18n';
import { register } from '@/routes';
import {
    create as auctionsCreate,
    index as auctionsIndex,
} from '@/routes/auctions';

export default function Welcome() {
    const { t } = useTranslation();
    const { auth } = usePage().props;
    const user = auth.user;

    const spotlight =
        heroAssets.find((hero) => hero.name === 'Gusion') ?? heroAssets[0];

    const primaryHref = user ? auctionsCreate.url() : register.url();
    const primaryLabel = user ? t('nav.sell') : t('welcome.register');

    const steps = [
        {
            no: '01',
            image: '/images/illustrations/searching.svg',
            title: t('welcome.steps.browse_title'),
            body: t('welcome.steps.browse_body'),
        },
        {
            no: '02',
            image: '/images/illustrations/winners.svg',
            title: t('welcome.steps.win_title'),
            body: t('welcome.steps.win_body'),
        },
        {
            no: '03',
            image: '/images/illustrations/mobile-payments.svg',
            title: t('welcome.steps.pay_title'),
            body: t('welcome.steps.pay_body'),
        },
    ];

    const features = [
        {
            icon: <ShieldIcon />,
            title: t('welcome.features.secure_title'),
            body: t('welcome.features.secure_body'),
        },
        {
            icon: <BoltIcon />,
            title: t('welcome.features.realtime_title'),
            body: t('welcome.features.realtime_body'),
        },
        {
            icon: <ChatIcon />,
            title: t('welcome.features.chat_title'),
            body: t('welcome.features.chat_body'),
        },
    ];

    return (
        <AppLayout title={t('welcome.title')}>
            <section
                data-section="storefront-hero"
                className="border-b border-border-default bg-neutral-secondary-soft"
            >
                <div className="mx-auto grid w-full max-w-[1152px] items-center gap-12 px-6 py-16 lg:grid-cols-[1.05fr_1fr] lg:py-24">
                    <div>
                        <span className="inline-flex items-center gap-2 border border-border-brand-subtle bg-brand-softer px-3 py-1 text-xs font-medium text-fg-brand-strong">
                            <span className="h-1.5 w-1.5 rounded-full bg-brand" />
                            {t('welcome.hero.eyebrow')}
                        </span>

                        <h1 className="mt-6 font-heading text-4xl font-bold text-heading md:text-5xl">
                            {t('welcome.headline')}
                        </h1>
                        <p className="mt-5 max-w-xl text-lg text-body">
                            {t('welcome.subheadline')}
                        </p>

                        <div className="mt-8 flex flex-wrap items-center gap-4">
                            <Link
                                href={primaryHref}
                                className={buttonClasses(
                                    'brand',
                                    'px-6 py-3.5 text-sm',
                                )}
                            >
                                {primaryLabel}
                            </Link>
                            <Link
                                href={auctionsIndex.url()}
                                className={buttonClasses(
                                    'white',
                                    'border border-border-default px-6 py-3.5 text-sm',
                                )}
                            >
                                {t('welcome.cta_browse')}
                            </Link>
                        </div>

                        <dl className="mt-12 flex flex-wrap gap-x-10 gap-y-4">
                            <div>
                                <dt className="text-xs font-medium tracking-wide text-body-subtle uppercase">
                                    {t('welcome.hero.stat_accounts_label')}
                                </dt>
                                <dd className="font-heading text-2xl font-bold text-heading">
                                    {t('welcome.hero.stat_accounts_value')}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs font-medium tracking-wide text-body-subtle uppercase">
                                    {t('welcome.hero.stat_heroes_label')}
                                </dt>
                                <dd className="font-heading text-2xl font-bold text-heading">
                                    {t('welcome.hero.stat_heroes_value')}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs font-medium tracking-wide text-body-subtle uppercase">
                                    {t('welcome.hero.stat_safe_label')}
                                </dt>
                                <dd className="font-heading text-2xl font-bold text-heading">
                                    {t('welcome.hero.stat_safe_value')}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <div className="relative mx-auto w-full max-w-md pb-6">
                        <div className="border border-border-default bg-neutral-primary-soft p-3 shadow-xs">
                            <div className="relative aspect-[4/3] overflow-hidden bg-neutral-secondary-soft">
                                <img
                                    src={spotlight.src}
                                    alt={spotlight.name}
                                    className="h-full w-full object-cover"
                                />
                                <span className="absolute top-3 left-3 inline-flex items-center gap-2 border border-border-danger-subtle bg-danger-soft px-2.5 py-1 text-xs font-semibold text-fg-danger-strong">
                                    <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-danger" />
                                    {t('welcome.hero.live')}
                                </span>
                            </div>
                            <div className="flex items-end justify-between gap-4 px-2 pt-4 pb-1">
                                <div>
                                    <p className="font-heading text-base font-bold text-heading">
                                        {t('welcome.hero.account')}
                                    </p>
                                    <p className="mt-1 text-xs text-body-subtle">
                                        {t('welcome.hero.bidders', {
                                            count: '1.248',
                                        })}
                                    </p>
                                </div>
                                <div className="text-right">
                                    <p className="text-[11px] font-medium tracking-wide text-body-subtle uppercase">
                                        {t('welcome.hero.current_bid')}
                                    </p>
                                    <p className="font-heading text-xl font-bold text-fg-gold">
                                        {t('welcome.hero.bid_value')}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div className="absolute -bottom-1 left-1/2 flex w-max -translate-x-1/2 items-center gap-3 border border-border-default bg-neutral-primary-soft px-4 py-3 shadow-xs">
                            <div className="flex -space-x-2">
                                {heroAssets.slice(0, 5).map((hero) => (
                                    <img
                                        key={hero.name}
                                        src={hero.src}
                                        alt={hero.name}
                                        className="h-8 w-8 rounded-full border-2 border-neutral-primary-soft object-cover"
                                    />
                                ))}
                            </div>
                            <span className="text-xs font-medium text-body">
                                {t('welcome.hero.popular')}
                            </span>
                        </div>
                    </div>
                </div>
            </section>

            <section className="border-b border-border-default">
                <div className="mx-auto w-full max-w-[1152px] px-6 py-10">
                    <p className="text-center text-sm text-body-subtle">
                        {t('welcome.heroes.title')}
                    </p>
                    <div className="mt-6 flex flex-wrap items-center justify-center gap-x-5 gap-y-6">
                        {heroAssets.map((hero) => (
                            <div
                                key={hero.name}
                                className="flex w-16 flex-col items-center gap-2"
                            >
                                <div className="h-14 w-14 overflow-hidden rounded-full border border-border-default bg-neutral-secondary-soft">
                                    <img
                                        src={hero.src}
                                        alt={hero.name}
                                        loading="lazy"
                                        className="h-full w-full object-cover"
                                    />
                                </div>
                                <span className="text-xs text-body-subtle">
                                    {hero.name}
                                </span>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            <section className="mx-auto w-full max-w-[1152px] px-6 py-16 lg:py-24">
                <div className="max-w-2xl">
                    <h2 className="font-heading text-3xl font-bold text-heading">
                        {t('welcome.steps.title')}
                    </h2>
                    <p className="mt-3 text-base text-body">
                        {t('welcome.steps.subtitle')}
                    </p>
                </div>

                <div className="mt-12 grid gap-8 md:grid-cols-3">
                    {steps.map((step) => (
                        <article
                            key={step.no}
                            className="flex flex-col border border-border-default bg-neutral-primary-soft p-6 shadow-xs"
                        >
                            <span className="font-heading text-sm font-bold text-fg-brand">
                                {step.no}
                            </span>
                            <div className="mt-5 flex h-44 items-center justify-center bg-white p-6">
                                <img
                                    src={step.image}
                                    alt=""
                                    loading="lazy"
                                    className="max-h-full w-auto"
                                />
                            </div>
                            <h3 className="mt-6 font-heading text-lg font-bold text-heading">
                                {step.title}
                            </h3>
                            <p className="mt-2 text-sm text-body">
                                {step.body}
                            </p>
                        </article>
                    ))}
                </div>
            </section>

            <section className="border-y border-border-default bg-neutral-secondary-soft">
                <div className="mx-auto grid w-full max-w-[1152px] items-center gap-12 px-6 py-16 lg:grid-cols-2 lg:py-24">
                    <div>
                        <h2 className="font-heading text-3xl font-bold text-heading">
                            {t('welcome.features.title')}
                        </h2>
                        <p className="mt-3 max-w-lg text-base text-body">
                            {t('welcome.features.subtitle')}
                        </p>

                        <ul className="mt-8 space-y-6">
                            {features.map((feature) => (
                                <li key={feature.title} className="flex gap-4">
                                    <span className="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center border border-border-brand-subtle bg-brand-softer text-fg-brand-strong">
                                        {feature.icon}
                                    </span>
                                    <div>
                                        <h3 className="font-heading text-base font-bold text-heading">
                                            {feature.title}
                                        </h3>
                                        <p className="mt-1 text-sm text-body">
                                            {feature.body}
                                        </p>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </div>

                    <div className="flex items-center justify-center bg-white p-8">
                        <img
                            src="/images/illustrations/mobile-gaming.svg"
                            alt=""
                            loading="lazy"
                            className="w-full max-w-md"
                        />
                    </div>
                </div>
            </section>

            <section className="bg-brand">
                <div className="mx-auto flex w-full max-w-[1152px] flex-col items-start gap-6 px-6 py-16 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 className="font-heading text-3xl font-bold text-white dark:text-neutral-primary">
                            {t('welcome.cta.title')}
                        </h2>
                        <p className="mt-2 max-w-xl text-on-brand-muted">
                            {t('welcome.cta.body')}
                        </p>
                    </div>
                    <Link
                        href={primaryHref}
                        className={buttonClasses(
                            'white',
                            'shrink-0 px-6 py-3.5 text-sm',
                        )}
                    >
                        {primaryLabel}
                    </Link>
                </div>
            </section>
        </AppLayout>
    );
}

function ShieldIcon() {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.8"
            className="h-5 w-5"
            aria-hidden="true"
        >
            <path
                d="M12 3l7 3v5c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6l7-3z"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
            <path
                d="M9.5 12l1.8 1.8L15 10"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}

function BoltIcon() {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.8"
            className="h-5 w-5"
            aria-hidden="true"
        >
            <path
                d="M13 2L4 14h7l-1 8 9-12h-7l1-8z"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}

function ChatIcon() {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.8"
            className="h-5 w-5"
            aria-hidden="true"
        >
            <path
                d="M5 5h14v10H8l-3 3V5z"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}
