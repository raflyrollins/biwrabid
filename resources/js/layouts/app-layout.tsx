import { Head, Link, router, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { FlashResult } from '@/components/flash-result';
import { buttonClasses } from '@/components/ui/button';
import { useTranslation } from '@/lib/i18n';
import { home, login, logout, register } from '@/routes';
import {
    create as auctionsCreate,
    index as auctionsIndex,
} from '@/routes/auctions';
import { index as myAuctionsIndex } from '@/routes/my-auctions';
import chat from '@/routes/chat';

type AppLayoutProps = {
    title: string;
    children: ReactNode;
};

export function AppLayout({ title, children }: AppLayoutProps) {
    const { t } = useTranslation();
    const { name, auth } = usePage().props;
    const user = auth.user;

    return (
        <div
            data-surface="ecommerce-app"
            className="flex min-h-screen flex-col bg-neutral-primary-soft"
        >
            <Head title={title} />

            <header className="border-b border-border-default">
                <div className="mx-auto flex w-full max-w-[1152px] items-center justify-between gap-4 px-6 py-4">
                    <div className="flex items-center gap-8">
                        <Link
                            href={home.url()}
                            className="flex items-center gap-2"
                        >
                            <span className="flex h-8 w-8 items-center justify-center bg-brand font-heading text-base font-bold text-on-brand dark:text-neutral-primary">
                                {name.charAt(0).toUpperCase()}
                            </span>
                            <span className="font-heading text-lg font-bold text-heading">
                                {name}
                            </span>
                        </Link>
                        <nav className="hidden items-center gap-6 md:flex">
                            <Link
                                href={auctionsIndex.url()}
                                className="text-sm font-medium text-body hover:text-heading"
                            >
                                {t('nav.auctions')}
                            </Link>
                            {user ? (
                                <>
                                    <Link
                                        href={myAuctionsIndex.url()}
                                        className="text-sm font-medium text-body hover:text-heading"
                                    >
                                        {t('nav.my_auctions')}
                                    </Link>
                                    <Link
                                        href={chat.index.url()}
                                        className="text-sm font-medium text-body hover:text-heading"
                                    >
                                        {t('nav.chat')}
                                    </Link>
                                </>
                            ) : null}
                        </nav>
                    </div>

                    <div className="flex items-center gap-4">
                        {user ? (
                            <>
                                <span className="hidden text-sm text-body-subtle sm:inline">
                                    {t('welcome.greeting', { name: user.name })}
                                </span>
                                <Link
                                    href={auctionsCreate.url()}
                                    className={buttonClasses(
                                        'brand',
                                        'px-4 py-2.5 text-sm',
                                    )}
                                >
                                    {t('nav.sell')}
                                </Link>
                                <button
                                    type="button"
                                    onClick={() => router.post(logout.url())}
                                    className="text-sm font-medium text-fg-brand underline underline-offset-4 hover:no-underline"
                                >
                                    {t('welcome.logout')}
                                </button>
                            </>
                        ) : (
                            <>
                                <Link
                                    href={login.url()}
                                    className="text-sm font-medium text-fg-brand underline underline-offset-4 hover:no-underline"
                                >
                                    {t('welcome.login')}
                                </Link>
                                <Link
                                    href={register.url()}
                                    className={buttonClasses(
                                        'brand',
                                        'px-4 py-2.5 text-sm',
                                    )}
                                >
                                    {t('welcome.register')}
                                </Link>
                            </>
                        )}
                    </div>
                </div>
            </header>

            <main className="flex-1">{children}</main>

            <FlashResult />

            <footer className="border-t border-border-default bg-neutral-secondary-soft">
                <div className="mx-auto w-full max-w-[1152px] px-6 py-12">
                    <div className="grid gap-10 md:grid-cols-[2fr_1fr_1fr]">
                        <div>
                            <span className="font-heading text-lg font-bold text-heading">
                                {name}
                            </span>
                            <p className="mt-3 max-w-sm text-sm text-body-subtle">
                                {t('welcome.footer.tagline')}
                            </p>
                        </div>

                        <nav className="flex flex-col gap-3">
                            <span className="text-xs font-semibold tracking-wide text-body-subtle uppercase">
                                {t('welcome.footer.explore')}
                            </span>
                            <Link
                                href={auctionsIndex.url()}
                                className="text-sm text-body hover:text-heading"
                            >
                                {t('nav.auctions')}
                            </Link>
                            <Link
                                href={home.url()}
                                className="text-sm text-body hover:text-heading"
                            >
                                {t('nav.home')}
                            </Link>
                        </nav>

                        <nav className="flex flex-col gap-3">
                            <span className="text-xs font-semibold tracking-wide text-body-subtle uppercase">
                                {t('welcome.footer.account')}
                            </span>
                            {user ? (
                                <>
                                    <Link
                                        href={myAuctionsIndex.url()}
                                        className="text-sm text-body hover:text-heading"
                                    >
                                        {t('nav.my_auctions')}
                                    </Link>
                                    <Link
                                        href={auctionsCreate.url()}
                                        className="text-sm text-body hover:text-heading"
                                    >
                                        {t('welcome.footer.sell')}
                                    </Link>
                                </>
                            ) : (
                                <>
                                    <Link
                                        href={register.url()}
                                        className="text-sm text-body hover:text-heading"
                                    >
                                        {t('welcome.register')}
                                    </Link>
                                    <Link
                                        href={login.url()}
                                        className="text-sm text-body hover:text-heading"
                                    >
                                        {t('welcome.login')}
                                    </Link>
                                </>
                            )}
                        </nav>
                    </div>

                    <p className="mt-10 border-t border-border-default pt-6 text-xs text-body-subtle">
                        {t('welcome.footer.disclaimer')}
                    </p>
                </div>
            </footer>
        </div>
    );
}
