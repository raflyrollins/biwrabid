import { Link } from '@inertiajs/react';
import type { PaginationLink } from '@/types/auction';
import { cn } from '@/lib/utils';

function decodeLabel(label: string): string {
    return label
        .replace('&laquo;', '«')
        .replace('&raquo;', '»')
        .replace('&hellip;', '…');
}

export function Pagination({ links }: { links: PaginationLink[] }) {
    if (links.length <= 3) {
        return null;
    }

    return (
        <nav
            aria-label="Pagination"
            className="flex flex-wrap items-center gap-px"
        >
            {links.map((link, index) => {
                if (!link.url) {
                    return (
                        <span
                            key={index}
                            className="flex h-9 min-w-9 items-center justify-center border border-border-default-medium bg-neutral-secondary-medium px-3 text-sm font-medium text-fg-disabled"
                        >
                            {decodeLabel(link.label)}
                        </span>
                    );
                }

                return (
                    <Link
                        key={index}
                        href={link.url}
                        preserveScroll
                        aria-current={link.active ? 'page' : undefined}
                        className={cn(
                            'flex h-9 min-w-9 items-center justify-center border border-border-default-medium px-3 text-sm font-medium',
                            link.active
                                ? 'bg-neutral-tertiary-medium text-fg-brand'
                                : 'bg-neutral-secondary-medium text-body hover:bg-neutral-tertiary-medium hover:text-heading',
                        )}
                    >
                        {decodeLabel(link.label)}
                    </Link>
                );
            })}
        </nav>
    );
}
