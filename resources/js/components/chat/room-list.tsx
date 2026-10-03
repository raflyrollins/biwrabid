import { usePage } from '@inertiajs/react';
import { MessageIcon } from '@/components/icons';
import { ScrollArea } from '@/components/scroll-area';
import { Badge } from '@/components/ui/badge';
import { formatRelativeTime } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { ChatParticipantRole, ChatRoom } from '@/types/chat';

type RoomListProps = {
    rooms: ChatRoom[];
    /** The room currently open. */
    activeUuid: string | null;
    /**
     * Called with the room to open, or null to close the thread and show the list
     * again on a phone.
     *
     * A plain callback rather than a link: choosing a conversation must not be a
     * navigation. The thread loads into the panel beside this list, so a `Link`
     * would throw the whole page away and land the admin on a different screen
     * with a different URL for what is one click.
     */
    onSelect: (uuid: string | null) => void;
    className?: string;
};

const roleVariant: Record<ChatParticipantRole, 'brand' | 'success' | 'dark'> = {
    member: 'brand',
    seller: 'success',
    winner: 'brand',
    admin: 'dark',
};

/**
 * Every conversation the viewer is in, one row each.
 *
 * Sits beside the thread on desktop and stands alone on a phone, and is the only
 * place a conversation is chosen from — so the admin inbox and a member's own
 * list are the same component with different rooms in it.
 */
export function RoomList({
    rooms,
    activeUuid,
    onSelect,
    className,
}: RoomListProps) {
    const { t } = useTranslation();
    const { locale } = usePage().props;

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
        <ScrollArea
            className={cn('overflow-y-auto overscroll-contain', className)}
        >
            <ul className="divide-y divide-border-default">
                {rooms.map((room) => {
                    const active = room.uuid === activeUuid;

                    return (
                        <li key={room.uuid}>
                            <button
                                type="button"
                                onClick={() => onSelect(room.uuid)}
                                aria-current={active ? 'true' : undefined}
                                className={cn(
                                    'flex w-full items-start gap-4 px-5 py-4 text-left transition-colors duration-150',
                                    active
                                        ? 'bg-brand-softer'
                                        : 'hover:bg-neutral-tertiary',
                                )}
                            >
                                {room.auction?.image_url ? (
                                    <img
                                        src={room.auction.image_url}
                                        alt=""
                                        loading="lazy"
                                        className="size-12 shrink-0 border border-border-default object-cover"
                                    />
                                ) : (
                                    <span className="flex size-12 shrink-0 items-center justify-center bg-neutral-secondary-medium text-fg-brand">
                                        <MessageIcon className="size-5" />
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
                                                    room.kind === 'auction'
                                                        ? 'dark'
                                                        : 'brand'
                                                }
                                            >
                                                {t(`chat.kinds.${room.kind}`)}
                                            </Badge>
                                        ) : null}
                                        {room.role ? (
                                            <Badge
                                                variant={roleVariant[room.role]}
                                            >
                                                {t(`chat.roles.${room.role}`)}
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
                            </button>
                        </li>
                    );
                })}
            </ul>
        </ScrollArea>
    );
}
