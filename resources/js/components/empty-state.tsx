import type { ReactNode } from 'react';

type EmptyStateProps = {
    image: string;
    title: string;
    description?: string;
    action?: ReactNode;
};

export function EmptyState({
    image,
    title,
    description,
    action,
}: EmptyStateProps) {
    return (
        <div className="flex flex-col items-center border border-border-default bg-neutral-secondary-soft px-6 py-14 text-center">
            <div className="w-full max-w-xs bg-white p-6">
                <img
                    src={image}
                    alt=""
                    loading="lazy"
                    className="animate-illustration-in mx-auto w-full"
                />
            </div>
            <h2 className="mt-8 font-heading text-xl font-bold text-heading">
                {title}
            </h2>
            {description ? (
                <p className="mt-2 max-w-md text-sm text-body-subtle">
                    {description}
                </p>
            ) : null}
            {action ? <div className="mt-6">{action}</div> : null}
        </div>
    );
}
