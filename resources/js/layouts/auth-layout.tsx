import { Head, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

type AuthLayoutProps = {
    title: string;
    subtitle?: string;
    children: ReactNode;
};

export function AuthLayout({ title, subtitle, children }: AuthLayoutProps) {
    const { name } = usePage().props;

    return (
        <div className="flex min-h-screen items-center justify-center bg-neutral-secondary-soft px-6 py-12">
            <Head title={title} />
            <div className="w-full max-w-md">
                <div className="mb-8 flex flex-col items-center gap-3">
                    <span className="flex h-12 w-12 items-center justify-center bg-brand font-heading text-2xl font-bold text-on-brand dark:text-neutral-primary">
                        {name.charAt(0).toUpperCase()}
                    </span>
                    <span className="font-heading text-xl font-bold text-heading">
                        {name}
                    </span>
                </div>

                <div className="border border-border-default bg-neutral-primary-soft p-8 shadow-xs">
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
    );
}
