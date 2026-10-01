import { Head, Link, router, usePage } from '@inertiajs/react';
import { buttonClasses } from '@/components/ui/button';
import { useTranslation } from '@/lib/i18n';
import { login, logout, register } from '@/routes';

export default function Welcome() {
    const { t } = useTranslation();
    const { name, auth } = usePage().props;
    const user = auth.user;

    return (
        <>
            <Head title={t('welcome.title')} />

            <div className="flex min-h-screen flex-col bg-neutral-primary-soft">
                <header className="border-b border-border-default">
                    <div className="mx-auto flex w-full max-w-[1152px] items-center justify-between gap-4 px-6 py-4">
                        <span className="font-heading text-xl font-bold text-heading">
                            {name}
                        </span>

                        <nav className="flex items-center gap-6">
                            {user ? (
                                <>
                                    <span className="text-sm text-body">
                                        {t('welcome.greeting', {
                                            name: user.name,
                                        })}
                                    </span>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            router.post(logout.url())
                                        }
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
                                            'px-5 py-2.5 text-sm',
                                        )}
                                    >
                                        {t('welcome.register')}
                                    </Link>
                                </>
                            )}
                        </nav>
                    </div>
                </header>

                <main className="flex flex-1 items-center bg-brand">
                    <div className="mx-auto flex w-full max-w-[1152px] flex-col items-start gap-6 px-6 py-24">
                        <h1 className="max-w-3xl font-heading text-4xl font-bold text-white md:text-6xl">
                            {t('welcome.headline')}
                        </h1>
                        <p className="max-w-2xl text-lg text-on-brand-muted">
                            {t('welcome.subheadline')}
                        </p>

                        {!user && (
                            <div className="mt-4 flex items-center gap-8">
                                <Link
                                    href={register.url()}
                                    className={buttonClasses('white')}
                                >
                                    {t('welcome.register')}
                                </Link>
                                <Link
                                    href={login.url()}
                                    className="text-base font-medium text-white underline underline-offset-4 hover:no-underline"
                                >
                                    {t('welcome.login')}
                                </Link>
                            </div>
                        )}
                    </div>
                </main>
            </div>
        </>
    );
}
