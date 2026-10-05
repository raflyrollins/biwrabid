import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { RoomList } from '@/components/chat/room-list';
import { ChatThreadPanel } from '@/components/chat/chat-thread-panel';
import { EmptyState } from '@/components/empty-state';
import { buttonClasses } from '@/components/ui/button';
import { useConfirm } from '@/hooks/use-confirm';
import { useRoomListPoll } from '@/hooks/use-room-list-poll';
import { useUnreadRooms } from '@/hooks/use-unread-rooms';
import { AppLayout } from '@/layouts/app-layout';
import { useTranslation } from '@/lib/i18n';
import { dismiss as dismissRoom, index as chatIndex } from '@/routes/chat';
import type { Paginated } from '@/types/auction';
import type { ChatRoom, ChatThread } from '@/types/chat';

type IndexProps = {
    rooms: Paginated<ChatRoom>;
    /** The open conversation, or null when the list is showing on its own. */
    room: ChatRoom | null;
    thread: ChatThread | null;
    /** A conversation started but not written into yet, or null. */
    draft: ChatDraft | null;
    isAdminInbox: boolean;
    maxLength: number;
    maxAttachments: number;
};

export type ChatDraft = 'support';

/**
 * The chat workspace: the conversations on one side, the open thread on the
 * other.
 *
 * One page for everyone. A member sees the rooms they are in and the admin sees
 * every room they moderate, and neither difference is expressed in the layout —
 * it is the same component with a different list, so the two cannot drift into
 * looking like different products.
 *
 * Choosing a conversation is a partial visit carrying `?c={uuid}` rather than a
 * link to another screen. The URL stays on `/chat`, so an admin working through
 * their inbox is not one navigation away from losing the list, and the thread
 * opens beside it.
 */
export default function ChatIndex({
    rooms,
    room,
    thread,
    draft,
    isAdminInbox,
    maxLength,
    maxAttachments,
}: IndexProps) {
    const { t } = useTranslation();
    const { auth } = usePage().props;
    const user = auth.user;
    const { ask, dialog } = useConfirm();

    const selected = room?.uuid ?? null;
    const empty = rooms.data.length === 0;
    const unread = useUnreadRooms(rooms.data, selected);

    /**
     * The room currently being fetched.
     *
     * A second click during that fetch is ignored: the visits race, and whichever
     * response lands last wins, so a fast double click on two conversations could
     * leave the thread showing one room's header and another room's messages.
     *
     * `ref`, not state: this is read inside an event handler and never rendered,
     * and a state write would re-render the whole workspace — the list included —
     * on every click. The row that is opening gets its own visual mark from
     * `opening`, so the click that was ignored still reads as "already doing that".
     */
    const openingRef = useRef<string | null>(null);
    const [opening, setOpening] = useState<string | null>(null);

    useEffect(() => {
        // Whatever landed, the fetch is over: the thread on screen is now the room
        // the URL names.
        openingRef.current = null;
        setOpening(null);
    }, [selected, draft]);

    /*
     * The inbox is the one screen whose job is "what is waiting for me", and the
     * thread it opens is realtime while nothing is open was not. A slow poll of the
     * list prop covers that, and it stops as soon as a conversation is open because
     * Echo has that room.
     */
    useRoomListPoll(selected);

    /*
     * Escape closes the open conversation, the way it closes a dialog.
     *
     * Bound on the window rather than on the panel so it works wherever focus
     * happens to be, and skipped inside a text field: Escape in the composer should
     * not throw away a half-written message, and Escape in the search box is
     * already spoken for by clearing it.
     */
    useEffect(() => {
        if (selected === null && draft === null) {
            return;
        }

        function onKeyDown(event: KeyboardEvent) {
            if (event.key !== 'Escape' || event.defaultPrevented) {
                return;
            }

            const target = event.target as HTMLElement | null;
            const typing =
                target instanceof HTMLElement &&
                (target.tagName === 'INPUT' ||
                    target.tagName === 'TEXTAREA' ||
                    target.isContentEditable);

            if (!typing) {
                openRoom(null);
            }
        }

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, [selected, draft]);

    const title =
        room === null
            ? t('chat.index.title')
            : room.kind === 'support'
              ? t('chat.show.support_title')
              : t(
                    room.kind === 'group'
                        ? 'chat.show.group_title'
                        : 'chat.show.credentials_title',
                    { title: room.auction?.title ?? '' },
                );

    /**
     * Swap the open conversation without leaving the page.
     *
     * `only` keeps the room list out of the response — it has not changed, and
     * re-sending it would make every click in the inbox re-render the list. The
     * scroll position is *not* preserved: this is a different conversation, and
     * landing at the bottom of the new thread is what a chat app does.
     *
     * `replace` rather than `push`, so reading through the inbox does not fill
     * the back button with one entry per conversation; the thread is reached
     * from the list, which is still there.
     */
    function openRoom(uuid: string | null) {
        // Mid-flight: ignore. The cursor stays a pointer, so the row does not read
        // as disabled — it is already marked as opening, which is the honest thing
        // to tell somebody whose click did nothing.
        if (openingRef.current !== null) {
            return;
        }

        openingRef.current = uuid ?? '';
        setOpening(uuid);

        visit(uuid === null ? {} : { c: uuid });
    }

    /**
     * Open the composer's other half for a conversation that does not exist yet.
     *
     * Nothing is written: the room is created by the first message, so a member
     * who opens the composer, decides against it and navigates away never becomes
     * an empty row in the admin's inbox. The thread still opens beside the list on
     * desktop — starting a conversation is not a screen of its own either.
     */
    function openDraft(kind: ChatDraft) {
        visit({ new: kind });
    }

    /**
     * Remove a conversation from this viewer's list for good.
     *
     * Asked for first through `useConfirm`, because the row disappears from the
     * list and there is no way back — "undo" on a tombstone is a second inbox
     * nobody asked for. The room itself stays, for the other participants and for
     * the payment trail behind it.
     */
    function removeRoom(uuid: string) {
        const target = rooms.data.find((room) => room.uuid === uuid);

        if (target === undefined) {
            return;
        }

        ask({
            title: t('chat.actions.dismiss_room'),
            description: t('chat.actions.dismiss_room_hint'),
            confirmLabel: t('chat.actions.dismiss_room'),
            cancelLabel: t('chat.actions.edit_cancel'),
            tone: 'danger',
            onConfirm: () => {
                router.post(
                    dismissRoom.url(uuid),
                    {},
                    {
                        showProgress: false,
                        // Leaving the room open would strand the thread with a row
                        // that is no longer in the list beside it.
                        onSuccess: () => {
                            if (selected === uuid) {
                                openRoom(null);
                            }
                        },
                    },
                );
            },
        });
    }

    function visit(params: Record<string, string>) {
        router.get(chatIndex.url(), params, {
            only: ['room', 'thread', 'draft'],
            preserveScroll: false,
            preserveState: false,
            replace: true,
            showProgress: false,
        });
    }

    return (
        <AppLayout title={t('chat.index.title')}>
            <Head title={title} />

            {/* One confirmation dialog for the screen, per the `useConfirm` contract. */}
            {dialog}

            {/*
             * A framed panel rather than a full-bleed one.
             *
             * `max-w-[1152px]` is the same measure the navbar and every other page
             * uses, so the chat lines up under the logo instead of running past it to
             * the edges of the window. The border and the small radius are what stop it
             * reading as a page section: the workspace is an application surface, and
             * the frame is what says so. `my-5` keeps it off the navbar, which with no
             * gap made the two look like one element.
             *
             * **Grid, not flex-col.** This container holds two panels side by side, and
             * `flex-col` — which is what this used to say — stacks them, which put the
             * thread *underneath* the list. The two columns are a fixed 20rem for the
             * list and `minmax(0, 1fr)` for the thread: the `minmax(0, …)` rather than a
             * plain `1fr` is what stops a long message or a long room title from
             * stretching the column past the container's edge.
             *
             * The height is the viewport minus navbar, this margin and the matching one
             * at the bottom, so the page never scrolls: only the list and the transcript
             * move, which is what a chat is. `min-h-[30rem]` is the floor — below about
             * 480px of panel there is no room for a header, a message and a composer at
             * once, and a squeezed composer that cannot be reached is worse than a
             * little scrolling.
             */}
            <div className="mx-auto my-5 grid h-[calc(100dvh-9rem)] min-h-[30rem] w-full max-w-[1152px] grid-cols-1 overflow-hidden rounded-lg border border-border-default bg-neutral-primary-soft lg:grid-cols-[20rem_minmax(0,1fr)]">
                {/*
                 * On a phone only one half is ever on screen: the list until a
                 * conversation is chosen, then the thread with a back button that
                 * returns to the list. From `lg` up both are visible and this is simply
                 * the sidebar beside the thread.
                 */}
                <aside
                    className={cnSidebar(selected === null && draft === null)}
                    aria-label={t('chat.index.list_label')}
                >
                    {user && !isAdminInbox ? (
                        <div className="border-b border-border-default p-4">
                            <p className="font-heading text-sm font-bold text-heading">
                                {t('chat.index.start_support')}
                            </p>
                            <p className="mt-1 mb-3 text-sm text-body-subtle">
                                {t('chat.index.support_hint')}
                            </p>

                            {/*
                             * A button, not a form post: the room is created by the first
                             * message, so pressing this writes nothing at all.
                             */}
                            <button
                                type="button"
                                onClick={() => openDraft('support')}
                                className={buttonClasses(
                                    'brand',
                                    'w-full px-4 py-2.5 text-sm',
                                )}
                            >
                                {t('chat.index.start_support')}
                            </button>
                        </div>
                    ) : null}

                    {empty ? (
                        <div className="min-h-0 flex-1 overflow-y-auto">
                            <EmptyState
                                compact
                                image="/images/illustrations/empty-chat.svg"
                                title={t('chat.index.empty_title')}
                                description={t('chat.index.empty_body')}
                            />
                        </div>
                    ) : (
                        <RoomList
                            rooms={rooms.data}
                            activeUuid={selected}
                            onSelect={openRoom}
                            unread={unread}
                            opening={opening}
                            onDismiss={removeRoom}
                            className="flex-1"
                        />
                    )}
                </aside>

                <ChatThreadPanel
                    // Keyed on what is open, so switching conversations remounts
                    // the panel: that is what unsubscribes the previous room's echo
                    // channels and drops its pending bubbles. Without it a stale
                    // listener would keep answering for a room nobody is reading.
                    key={selected ?? draft ?? 'none'}
                    room={room}
                    thread={thread}
                    draft={draft}
                    maxLength={maxLength}
                    maxAttachments={maxAttachments}
                    onBack={() => openRoom(null)}
                />

                {/*
                 * The right half with nothing open. Desktop-only: on a phone there is no
                 * room for it, and the list alone already says which conversations there
                 * are.
                 *
                 * `min-w-0` because this is a grid item now: without it the centred
                 * illustration could push the column wider than the space left over
                 * from the list.
                 */}
                {selected === null && draft === null ? (
                    <div className="hidden min-h-0 min-w-0 flex-col items-center justify-center overflow-y-auto bg-neutral-secondary-soft/40 px-6 py-10 lg:flex">
                        <EmptyState
                            compact
                            image="/images/illustrations/empty-chat.svg"
                            title={
                                empty
                                    ? t('chat.index.empty_title')
                                    : t('chat.show.select_title')
                            }
                            description={
                                empty
                                    ? t('chat.index.empty_body')
                                    : t('chat.show.select_body')
                            }
                        />
                    </div>
                ) : null}
            </div>
        </AppLayout>
    );
}

/**
 * The list panel's own responsive visibility.
 *
 * Hidden while a thread is open *on a phone only* — from `lg` up it has to stay,
 * or switching conversations would mean going back to the list first. Written
 * here rather than inline so the one condition both halves depend on is a single
 * named expression instead of a repeated class string that can drift.
 *
 * `min-h-0`/`min-w-0` are what make the panel's own scroll work: a grid item
 * defaults to `min-height: auto`, so without them the list grows to fit every row
 * and pushes the page taller than the viewport instead of scrolling inside itself.
 */
function cnSidebar(open: boolean): string {
    return [
        'min-h-0 min-w-0 flex-col border-border-default lg:flex lg:border-r',
        open ? 'flex' : 'hidden lg:flex',
    ].join(' ');
}
