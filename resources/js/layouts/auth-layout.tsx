import { Head, Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useTranslation } from '@/lib/i18n';
import { home } from '@/routes';

type AuthLayoutProps = {
    title: string;
    subtitle?: string;
    illustration?: string;
    children: ReactNode;
};

const defaultIllustration = '/images/illustrations/secure-login.svg';

export function AuthLayout({
    title,
    subtitle,
    illustration = defaultIllustration,
    children,
}: AuthLayoutProps) {
    const { name } = usePage().props;
    const { t } = useTranslation();

    const points = [
        t('auth.panel.point_secure'),
        t('auth.panel.point_realtime'),
        t('auth.panel.point_chat'),
    ];

    return (
        <div className="grid min-h-screen bg-neutral-primary-soft lg:grid-cols-2">
            <Head title={title} />

            <div className="flex flex-col px-6 py-8 sm:px-12">
                <Link
                    href={home.url()}
                    className="inline-flex items-center gap-2 self-start"
                >
                    <span className="flex h-9 w-9 items-center justify-center bg-brand font-heading text-lg font-bold text-on-brand dark:text-neutral-primary">
                        {name.charAt(0).toUpperCase()}
                    </span>
                    <span className="font-heading text-lg font-bold text-heading">
                        {name}
                    </span>
                </Link>

                <div className="flex flex-1 items-center justify-center py-10">
                    <div className="w-full max-w-sm">
                        <h1 className="font-heading text-2xl font-bold text-heading">
                            {title}
                        </h1>
                        {subtitle ? (
                            <p className="mt-2 text-sm text-body-subtle">
                                {subtitle}
                            </p>
                        ) : null}
                        <div className="mt-8">{children}</div>
                    </div>
                </div>
            </div>

            <aside className="hidden overflow-hidden bg-brand px-14 py-12 lg:flex lg:flex-col">
                <div className="flex flex-1 items-center justify-center py-8">
                    <div className="w-full max-w-md bg-white p-8">
                        <img src={illustration} alt="" className="w-full" />
                    </div>
                </div>
                <div>
                    <h2 className="font-heading text-2xl font-bold text-white dark:text-neutral-primary">
                        {t('auth.panel.title')}
                    </h2>
                    <p className="mt-3 max-w-md text-on-brand-muted">
                        {t('auth.panel.body')}
                    </p>
                    <ul className="mt-6 space-y-3">
                        {points.map((point) => (
                            <li
                                key={point}
                                className="flex items-center gap-3 text-sm font-medium text-white dark:text-neutral-primary"
                            >
                                <CheckIcon />
                                {point}
                            </li>
                        ))}
                    </ul>
                </div>
            </aside>
        </div>
    );
}

function CheckIcon() {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            className="h-4 w-4 shrink-0"
            aria-hidden="true"
        >
            <path
                d="M20 6L9 17l-5-5"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}
