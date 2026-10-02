import { Head, Link, usePage } from '@inertiajs/react';
import { EmptyState } from '@/components/empty-state';
import { MessageIcon } from '@/components/icons';
import { Badge } from '@/components/ui/badge';
import { buttonClasses } from '@/components/ui/button';
import { AppLayout } from '@/layouts/app-layout';
import { formatRelativeTime } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';
import chat from '@/routes/chat';
import type { Paginated } from '@/types/auction';
import type { ChatParticipantRole, ChatRoom } from '@/types/chat';

type IndexProps = {
    rooms: Paginated<ChatRoom>;
    isAdminInbox: boolean;
};

const roleVariant: Record<ChatParticipantRole, 'brand' | 'success' | 'dark'> = {
    member: 'brand',
    seller: 'success',
    winner: 'brand',
    admin: 'dark',
};

export default function ChatIndex({ rooms, isAdminInbox }: IndexProps) {
    const { t } = useTranslation();
    const { auth, locale } = usePage().props;
    const user = auth.user;

    /**
     * Support is generic, while both auction rooms name the auction. The group
     * thread and the credential handoff are otherwise indistinguishable in the
     * list, which would leave the reader guessing where they are about to type
     * a password.
     */
    function titleFor(room: ChatRoom): string {
        if (room.kind === 'support') {
            return t('chat.show.support_title');
        }

        const key =
            room.kind === 'group'
                ? 'chat.show.group_title'
                : 'chat.show.credentials_title';

        return t(key, { title: room.auction?.title ?? '' });
    }

    function subtitleFor(room: ChatRoom): string {
        return room.counterparties.map((person) => person.name).join(', ');
    }

    return (
        <AppLayout title={t('chat.index.title')}>
            <Head title={t('chat.index.title')} />

            <div className="mx-auto w-full max-w-[1152px] px-6 py-10">
                <header>
                    <h1 className="font-heading text-3xl font-bold text-heading">
                        {isAdminInbox
                            ? t('chat.index.admin_inbox_title')
                            : t('chat.index.heading')}
                    </h1>
                    <p className="mt-2 text-sm text-body-subtle">
                        {isAdminInbox
                            ? t('chat.index.admin_inbox_body')
                            : t('chat.index.subtitle')}
                    </p>
                </header>

                {user && !isAdminInbox ? (
                    <div className="mt-8 flex flex-col gap-3 border border-border-default bg-neutral-secondary-soft px-5 py-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <p className="font-heading text-sm font-bold text-heading">
                                {t('chat.index.start_support')}
                            </p>
                            <p className="mt-1 text-sm text-body-subtle">
                                {t('chat.index.support_hint')}
                            </p>
                        </div>
                        <Link
                            href={chat.support.url()}
                            method="post"
                            as="button"
                            className={buttonClasses(
                                'brand',
                                'shrink-0 px-4 py-2.5 text-sm',
                            )}
                        >
                            {t('chat.index.start_support')}
                        </Link>
                    </div>
                ) : null}

                <div className="mt-8">
                    {rooms.data.length === 0 ? (
                        <EmptyState
                            image="/images/illustrations/empty-chat.svg"
                            title={t('chat.index.empty_title')}
                            description={t('chat.index.empty_body')}
                        />
                    ) : (
                        <ul className="divide-y divide-border-default border border-border-default bg-neutral-secondary-soft">
                            {rooms.data.map((room) => (
                                <li key={room.uuid}>
                                    <Link
                                        href={chat.show.url(room.uuid)}
                                        className="flex items-start gap-4 px-5 py-4 transition-colors duration-150 hover:bg-neutral-tertiary"
                                    >
                                        {room.auction?.image_url ? (
                                            <img
                                                src={room.auction.image_url}
                                                alt=""
                                                loading="lazy"
                                                className="h-12 w-12 shrink-0 border border-border-default object-cover"
                                            />
                                        ) : (
                                            <span className="flex h-12 w-12 shrink-0 items-center justify-center bg-neutral-secondary-medium text-fg-brand">
                                                <MessageIcon className="h-5 w-5" />
                                            </span>
                                        )}

                                        <div className="min-w-0 flex-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="truncate font-heading text-sm font-bold text-heading">
                                                    {titleFor(room)}
                                                </span>
                                                {room.kind !== 'support' ? (
                                                    <Badge
                                                        variant={
                                                            room.kind ===
                                                            'auction'
                                                                ? 'dark'
                                                                : 'brand'
                                                        }
                                                    >
                                                        {t(
                                                            `chat.kinds.${room.kind}`,
                                                        )}
                                                    </Badge>
                                                ) : null}
                                                {room.role ? (
                                                    <Badge
                                                        variant={
                                                            roleVariant[
                                                                room.role
                                                            ]
                                                        }
                                                    >
                                                        {t(
                                                            `chat.roles.${room.role}`,
                                                        )}
                                                    </Badge>
                                                ) : null}
                                            </div>

                                            <p className="mt-1 truncate text-sm text-body-subtle">
                                                {subtitleFor(room) ||
                                                    room.latest_message?.body ||
                                                    t('chat.show.empty_title')}
                                            </p>
                                        </div>

                                        <span className="shrink-0 text-xs text-body-subtle">
                                            {formatRelativeTime(
                                                room.updated_at,
                                                locale,
                                            )}
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
