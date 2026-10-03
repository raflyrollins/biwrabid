import { Head, router, usePage } from '@inertiajs/react';
import { RoomList } from '@/components/chat/room-list';
import { ChatThreadPanel } from '@/components/chat/chat-thread-panel';
import { EmptyState } from '@/components/empty-state';
import { buttonClasses } from '@/components/ui/button';
import { AppLayout } from '@/layouts/app-layout';
import { useTranslation } from '@/lib/i18n';
import { index as chatIndex } from '@/routes/chat';
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

    const selected = room?.uuid ?? null;
    const empty = rooms.data.length === 0;

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

            <div className="mx-auto flex h-[calc(100dvh-4.5rem)] w-full max-w-[1152px] overflow-hidden bg-neutral-primary-soft">
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
                        <EmptyState
                            image="/images/illustrations/empty-chat.svg"
                            title={t('chat.index.empty_title')}
                            description={t('chat.index.empty_body')}
                        />
                    ) : (
                        <RoomList
                            rooms={rooms.data}
                            activeUuid={selected}
                            onSelect={openRoom}
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
                 */}
                {selected === null && draft === null ? (
                    <div className="hidden flex-1 flex-col items-center justify-center bg-neutral-secondary-soft/40 px-6 py-10 lg:flex">
                        <EmptyState
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
 */
function cnSidebar(open: boolean): string {
    return [
        'w-full shrink-0 flex-col border-border-default lg:flex lg:w-[22rem] lg:border-r',
        open ? 'flex' : 'hidden lg:flex',
    ].join(' ');
}
