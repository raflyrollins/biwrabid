import { usePage } from '@inertiajs/react';
import { useDeferredValue, useEffect, useMemo, useRef, useState } from 'react';
import { Avatar, roomAvatarName } from '@/components/chat/avatar';
import {
    ContextMenu,
    useContextMenu,
    type ContextMenuItem,
    type ContextMenuState,
} from '@/components/context-menu';
import { ScrollArea } from '@/components/scroll-area';
import { Badge } from '@/components/ui/badge';
import { formatRelativeTime } from '@/lib/format';
import { useTranslation, type Translate } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { ChatRoom } from '@/types/chat';

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
    /** Rooms with a message newer than the last time they were opened. */
    unread: Record<string, boolean>;
    /** The room being fetched, so a second click during it can be ignored. */
    opening?: string | null;
    /** Remove a conversation from this viewer's list for good. */
    onDismiss?: (uuid: string) => void;
    className?: string;
};

/**
 * At most two badges per row.
 *
 * A group thread can carry a kind, a role and a moderator marker; at full size
 * they wrap onto a second line and the row doubles in height, which is the exact
 * thing this list is trying to stop doing. Two is enough to say "this is a group
 * thread and you are the seller" — anything more is detail the header has.
 */
const maxBadges = 2;

/**
 * Every conversation the viewer is in, one row each, with a filter above them.
 *
 * Sits beside the thread on desktop and stands alone on a phone, and is the only
 * place a conversation is chosen from — so the admin inbox and a member's own list
 * are the same component with different rooms in it.
 *
 * The filter runs here rather than on the server because one page of rooms is at
 * most `chat.rooms_per_page` rows and already in memory: a request per keystroke
 * to narrow twenty rows would be a slower answer to a question the client can
 * answer.
 */
export function RoomList({
    rooms,
    activeUuid,
    onSelect,
    unread,
    opening,
    onDismiss,
    className,
}: RoomListProps) {
    const { t } = useTranslation();
    const { locale } = usePage().props;

    const [query, setQuery] = useState('');
    const [search, setSearch] = useState('');
    const inputRef = useRef<HTMLInputElement | null>(null);

    /*
     * Filtered on the deferred value rather than on every keystroke: React runs
     * this at a lower priority, so typing stays smooth while the list catches up.
     * It replaces the debounce timer rather than competing with one — same job,
     * one less effect to clean up.
     */
    const deferredQuery = useDeferredValue(query);
    const trimmed = deferredQuery.trim().toLowerCase();

    useEffect(() => {
        setSearch(trimmed);
    }, [trimmed]);

    const visible = useMemo(() => {
        if (search === '') {
            return rooms;
        }

        return rooms.filter((room) => {
            const haystack = [
                titleFor(room, t),
                ...room.counterparties.map(
                    (person) => person.name ?? t(`chat.roles.${person.role}`),
                ),
                room.latest_message?.body ?? '',
            ]
                .join(' ')
                .toLowerCase();

            return haystack.includes(search);
        });
    }, [rooms, search, t]);

    return (
        <div className="flex min-h-0 flex-1 flex-col">
            {/*
             * The filter sits above the list on both roles. On a member's side it is
             * under the "Chat with Admin" block, because that block is the first thing
             * they are offered; on the admin's side it is the top of the panel.
             */}
            <div className="shrink-0 border-b border-border-default p-2.5">
                <div className="relative">
                    <svg
                        aria-hidden="true"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth={2}
                        strokeLinecap="round"
                        className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-body-subtle"
                    >
                        <circle cx="11" cy="11" r="7" />
                        <path d="m20 20-3.5-3.5" />
                    </svg>

                    <input
                        ref={inputRef}
                        type="text"
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        onKeyDown={(event) => {
                            if (event.key === 'Escape' && query !== '') {
                                setQuery('');
                            }
                        }}
                        placeholder={t('chat.index.search')}
                        aria-label={t('chat.index.search')}
                        className="h-9 w-full rounded-lg border border-border-default bg-neutral-primary-soft pr-8 pl-8 text-sm text-heading placeholder:text-body-subtle focus:border-border-brand focus:outline-none"
                    />

                    {query !== '' ? (
                        <button
                            type="button"
                            onClick={() => {
                                setQuery('');
                                inputRef.current?.focus();
                            }}
                            className="absolute top-1/2 right-1.5 grid size-6 -translate-y-1/2 place-items-center rounded-full text-body-subtle hover:bg-neutral-secondary-soft hover:text-heading"
                            aria-label={t('chat.index.search_clear')}
                        >
                            ×
                        </button>
                    ) : null}
                </div>
            </div>

            <ScrollArea
                className={cn(
                    'min-h-0 flex-1 overflow-y-auto overscroll-contain',
                    className,
                )}
            >
                {visible.length === 0 ? (
                    <p className="px-4 py-8 text-center text-xs text-body-subtle">
                        {rooms.length === 0
                            ? t('chat.index.empty_body')
                            : t('chat.index.search_empty')}
                    </p>
                ) : (
                    <ul>
                        {visible.map((room) => (
                            <RoomRow
                                key={room.uuid}
                                room={room}
                                active={room.uuid === activeUuid}
                                opening={room.uuid === opening}
                                unread={unread[room.uuid] === true}
                                locale={locale}
                                onSelect={onSelect}
                                onDismiss={onDismiss}
                            />
                        ))}
                    </ul>
                )}
            </ScrollArea>
        </div>
    );
}

/**
 * One conversation in the list.
 *
 * Split out because it is rendered per row and carries all of the row's decisions —
 * which badges fit, whose avatar this is, what the preview says — and inlining it
 * would make the list unreadable at exactly the point where it has to stay
 * consistent between a member's list and the admin's.
 */
function RoomRow({
    room,
    active,
    opening,
    unread,
    locale,
    onSelect,
    onDismiss,
}: {
    room: ChatRoom;
    active: boolean;
    opening: boolean;
    unread: boolean;
    locale: string;
    onSelect: (uuid: string | null) => void;
    onDismiss?: (uuid: string) => void;
}) {
    const { t } = useTranslation();
    const [menu, setMenu] = useState<ContextMenuState | null>(null);

    const onContextMenu = useContextMenu(setMenu);

    const avatarName = roomAvatarName(room, t('chat.roles.admin'));
    const people = room.counterparties.map(
        (person) => person.name ?? t(`chat.roles.${person.role}`),
    );

    const badges = [
        room.kind !== 'support'
            ? {
                  key: room.kind,
                  label: t(`chat.kinds.${room.kind}`),
                  accent: false,
              }
            : null,
        room.role
            ? {
                  key: room.role,
                  label: t(`chat.roles.${room.role}`),
                  // The admin badge is the one accent: it is the only role that
                  // says something about who is responsible for the thread rather
                  // than who is in it.
                  accent: room.role === 'admin',
              }
            : null,
    ].filter((badge): badge is NonNullable<typeof badge> => badge !== null);

    const menuItems: ContextMenuItem[] = [
        {
            key: 'close',
            label: t('chat.actions.close_room'),
            onSelect: () => onSelect(null),
        },
    ];

    if (onDismiss !== undefined) {
        menuItems.push({
            key: 'dismiss',
            label: t('chat.actions.dismiss_room'),
            hint: t('chat.actions.dismiss_room_hint'),
            tone: 'danger',
            onSelect: () => onDismiss(room.uuid),
        });
    }

    return (
        <li>
            <button
                type="button"
                onClick={() => onSelect(room.uuid)}
                onContextMenu={onContextMenu}
                aria-current={active ? 'true' : undefined}
                aria-busy={opening || undefined}
                className={cn(
                    // `items-start` because the time sits in the top-right corner and
                    // the title is what the row is identified by: centring the block
                    // against a three-line stack left a gap under the avatar and made
                    // a 64px row look accidental.
                    'relative flex w-full items-start gap-2.5 border-b border-border-default py-2 pr-2.5 pl-3 text-left transition-colors duration-150',
                    active
                        ? 'bg-brand-softer'
                        : 'hover:bg-neutral-secondary-medium',
                    /*
                     * Mid-fetch, the row dims instead of going inert. A disabled row
                     * with a not-allowed cursor says "you cannot do this"; what
                     * actually happened is "this is already on its way", and the
                     * pointer stays a pointer either way.
                     */
                    opening && 'opacity-60',
                )}
            >
                {/*
                 * The accent bar rather than a heavier border: an active row has
                 * to be findable while scrolling, and a coloured edge reads at a
                 * glance where a tint alone does not.
                 */}
                {active ? (
                    <span
                        aria-hidden="true"
                        className="absolute inset-y-0 left-0 w-[3px] bg-brand"
                    />
                ) : null}

                <Avatar name={avatarName} size={32} />

                <div className="min-w-0 flex-1">
                    {/*
                     * The title gets the full width of the row on one line, badges
                     * underneath on their own line. Side by side, the badges took
                     * their width out of the title, which is the one thing in a list
                     * that must not be shortened — an auction title and "Grup Lelang"
                     * together need more width than a 20rem panel has, and the title
                     * lost.
                     */}
                    <p className="truncate font-heading text-[13px] leading-4 font-semibold text-heading">
                        {titleFor(room, t)}
                    </p>

                    {badges.length > 0 ? (
                        <div className="mt-1 flex items-center gap-1">
                            {badges.slice(0, maxBadges).map((badge) => (
                                <Badge
                                    key={badge.key}
                                    variant={badge.accent ? 'brand' : 'gray'}
                                    className={cn(
                                        'shrink-0 rounded border-transparent px-1.5 py-px text-[10px] leading-4 font-medium',
                                        badge.accent
                                            ? 'bg-brand text-on-brand'
                                            : 'bg-neutral-secondary-medium text-body',
                                    )}
                                >
                                    {badge.label}
                                </Badge>
                            ))}
                        </div>
                    ) : null}

                    <p className="mt-1 truncate text-xs leading-4 text-body-subtle">
                        {people.join(', ') ||
                            previewOf(room) ||
                            t('chat.show.empty_title')}
                    </p>
                </div>

                <div className="flex shrink-0 flex-col items-end gap-1">
                    <span className="text-[11px] text-body-subtle/80">
                        {formatRelativeTime(room.updated_at, locale)}
                    </span>

                    {/*
                     * The unread dot, derived from data already in the row: a room
                     * whose newest message is newer than the last time this viewer
                     * opened it. No extra query, and nothing to keep in sync on the
                     * server — the timestamp the list already has is the answer.
                     */}
                    {unread && !active ? (
                        <span
                            aria-label={t('chat.index.unread')}
                            className="size-2 rounded-full bg-brand"
                        />
                    ) : null}
                </div>
            </button>

            {/*
             * Right-click on a room is the same gesture as right-click in a chat
             * app: open, close, delete — here with a line saying that "delete"
             * removes the conversation from *your* list only, because that is the
             * one thing about it a person cannot see for themselves.
             */}
            {menu !== null ? (
                <ContextMenu
                    state={menu}
                    items={menuItems}
                    label={titleFor(room, t)}
                    onClose={() => setMenu(null)}
                />
            ) : null}
        </li>
    );
}

/**
 * Support is generic, while both auction rooms name the auction. The group thread
 * and the credential handoff are otherwise indistinguishable in the list, which
 * would leave the reader guessing where they are about to type a password.
 *
 * Takes `t` rather than calling the hook so the filter can reuse it without
 * turning `RoomList` into a component with two translation lookups per keystroke.
 */
function titleFor(room: ChatRoom, t: Translate): string {
    if (room.kind === 'support') {
        return t('chat.show.support_title');
    }

    const key =
        room.kind === 'group'
            ? 'chat.show.group_title'
            : 'chat.show.credentials_title';

    return t(key, { title: room.auction?.title ?? '' });
}

/**
 * The last thing said, for a room with nobody to name.
 *
 * A support room seen by its own member has no named counterpart — the other side
 * is "the admin" — so the row falls back to the conversation itself rather than
 * repeating the title on the second line.
 */
function previewOf(room: ChatRoom): string {
    return room.latest_message?.body ?? '';
}
