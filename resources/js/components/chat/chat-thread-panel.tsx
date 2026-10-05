import { router, useForm, usePage } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import {
    useCallback,
    useEffect,
    useMemo,
    useRef,
    useState,
    type ChangeEvent,
    type FormEvent,
} from 'react';
import { Avatar, roomAvatarName } from '@/components/chat/avatar';
import {
    ContextMenu,
    useContextMenu,
    type ContextMenuItem,
    type ContextMenuState,
} from '@/components/context-menu';
import { PaymentQrisCard } from '@/components/chat/payment-qris-card';
import {
    ArrowLeftIcon,
    CheckCheckIcon,
    CheckIcon,
    ClockIcon,
    DocumentIcon,
    PaperclipIcon,
    SendIcon,
    ShieldIcon,
    XCircleIcon,
} from '@/components/icons';
import { ScrollArea } from '@/components/scroll-area';
import { Badge } from '@/components/ui/badge';
import { buttonClasses } from '@/components/ui/button';
import { InputError } from '@/components/ui/input-error';
import {
    formatCurrency,
    formatDaySeparator,
    formatFileSize,
    formatTime,
    localDayKey,
} from '@/lib/format';
import { useTranslation } from '@/lib/i18n';
import { useFilePreviews } from '@/lib/use-file-previews';
import { cn } from '@/lib/utils';
import {
    destroy as destroyMessage,
    read as readReceipts,
    store as storeMessage,
    update as updateMessage,
} from '@/routes/chat/messages';
import { support as startSupport } from '@/routes/chat';
import type {
    ChatMessage,
    ChatMessageAttachment,
    ChatMessageKind,
    ChatRoom,
    ChatThread,
} from '@/types/chat';

/**
 * The composer's own payload, and the form object that carries it.
 *
 * Named because two components submit it: a real thread and the draft that
 * becomes one. Sharing the shape is what lets `ChatComposer` take either.
 */
type ChatMessageForm = {
    body: string;
    attachments: File[];
};

type ChatForm = ReturnType<typeof useForm<ChatMessageForm>>;

/** Mirrors `ChatRoomType`, minus the kinds that cannot be started by hand. */
export type ChatDraft = 'support';

/**
 * How tall the composer may grow, in lines, before it starts scrolling.
 *
 * Five is enough for the message somebody actually has to write (a dispute, an
 * account number) without the composer eating the thread above it. The pixel
 * figure is the same number against the textarea's line-height, and it has to
 * match the `max-h-*` on the field or the box stops exactly where the cap is
 * meant to be.
 */
const maxRows = 5;
const lineHeight = 24;

type ThreadPanelProps = {
    /** Null when no conversation is open: the panel renders nothing at all. */
    room: ChatRoom | null;
    thread: ChatThread | null;
    /**
     * A conversation that has been started but not written into yet.
     *
     * The panel renders as a thread with no room behind it: a header, an empty
     * transcript and a working composer whose first send creates the room. That is
     * what lets the "Chat with Admin" button write nothing at all — a thread
     * nobody has posted in is not a conversation, and the admin's inbox should not
     * be full of them.
     */
    draft?: ChatDraft | null;
    maxLength: number;
    maxAttachments: number;
    /**
     * Close the thread and go back to the list.
     *
     * Three callers on purpose: the back button on a phone, the right-click menu on
     * desktop, and the Escape key. One function rather than three, because "leave
     * this conversation" is one decision and three code paths for it would be three
     * chances to end up in different places.
     */
    onBack: () => void;
};

/** Echo payload of `chat.messages.read`. */
type MessagesReadPayload = {
    room_uuid: string;
    reader_id: number;
    /** The messages that just became read by every participant. */
    read_by_all_ids: number[];
};

type PendingState = 'sending' | 'failed';

/**
 * A message this viewer has sent that the server has not answered yet.
 *
 * Kept as its own shape rather than a `ChatMessage` with a fake id so the bubble
 * can be rendered as a pending one - a clock, no name, retryable - while the real
 * shape only ever describes something the server has stored.
 */
type PendingMessage = {
    /** Stable across the pending -> failed -> sent transitions, unlike the id. */
    key: string;
    body: string;
    files: File[];
    createdAt: string | null;
    state: PendingState;
};

const paymentBadge: Record<ChatMessageKind, 'brand' | 'success' | 'warning'> = {
    message: 'brand',
    payment_request: 'warning',
    payment_proof: 'warning',
    payment_received: 'success',
    transfer_proof: 'warning',
    payment_completed: 'success',
};

/**
 * How many read receipts one request may carry, matching the `max` on
 * `MarkMessagesReadRequest::rules()`.
 */
const maxReadIds = 100;

/**
 * The payment trail is not chatter: it is a record with evidence attached, so it
 * renders as a centred system line instead of a bubble that happens to have an
 * image in it. The trail is still readable in the thread - the state lives in
 * `auction_payments`, and the actions live on the auction page.
 */
function isPaymentTrail(kind: ChatMessageKind): boolean {
    return kind !== 'message';
}

function isPdf(name: string): boolean {
    return name.toLowerCase().endsWith('.pdf');
}

/**
 * One row in the rendered thread: either a date separator or a message.
 */
type Row =
    | { type: 'day'; key: string; label: string }
    | {
          type: 'message';
          key: string;
          message: ChatMessage;
          own: boolean;
          /** Continues the previous bubble: no name, tight spacing. */
          grouped: boolean;
      }
    | { type: 'pending'; key: string; pending: PendingMessage };

/**
 * Whether two messages close enough together read as one turn.
 *
 * Five minutes. Long enough that a reply to a message from yesterday is not glued
 * to it, short enough that someone typing a paragraph lands in the same run.
 */
const runGapMs = 5 * 60 * 1000;

function sameRun(previousAt: string | null, current: string | null): boolean {
    if (previousAt === null || current === null) {
        return false;
    }

    const gap = new Date(current).getTime() - new Date(previousAt).getTime();

    return gap >= 0 && gap < runGapMs;
}

/**
 * One conversation: header, transcript, composer.
 *
 * The same panel for every role. What differs between a member and the admin is
 * which rooms are in the list beside it and who is in them, never the way a
 * message is drawn, so an admin moderating a payment reads the same screen a
 * member does.
 *
 * The workspace keys this component on the room, so switching conversations
 * remounts it. That is what unsubscribes the previous room's echo channels and
 * drops its pending bubbles: without the remount, a stale listener would keep
 * answering for a room nobody is reading any more.
 */
export function ChatThreadPanel({
    room,
    thread,
    draft = null,
    maxLength,
    maxAttachments,
    onBack,
}: ThreadPanelProps) {
    const { t } = useTranslation();
    const { auth, currency, locale } = usePage().props;
    const user = auth.user;

    const [incoming, setIncoming] = useState<ChatMessage[]>([]);
    const [pending, setPending] = useState<PendingMessage[]>([]);
    /**
     * Messages another participant has finished reading, learned from the
     * broadcast rather than from a reload. The server is the only thing that
     * knows who *everybody* is in a room, so this is a list of ids it named, not
     * something worked out here.
     */
    const [readByAll, setReadByAll] = useState<number[]>([]);
    /** Ids already reported as read, so the thread does not re-report them. */
    const reportedRef = useRef<Set<number>>(new Set());

    /**
     * The message currently open in the inline editor, and the message the
     * right-click menu is open on.
     *
     * Two separate pieces of state because they answer different questions and
     * cancel differently: Escape in the editor puts the text back, Escape on the
     * menu just closes the menu.
     */
    const [editingId, setEditingId] = useState<number | null>(null);
    const [menu, setMenu] = useState<{
        state: ContextMenuState;
        message: ChatMessage;
    } | null>(null);

    /**
     * Messages removed while the thread was open — by somebody else, or by this
     * viewer in another tab.
     *
     * Held client-side rather than filtered from the response because a removal that
     * arrived over the socket has no page load behind it: without this the bubble
     * would sit on screen until somebody reloaded, which is exactly the state that
     * makes people not trust what they are looking at.
     */
    const [removed, setRemoved] = useState<Set<number>>(new Set());

    /*
     * The menu on the empty space of the thread, as opposed to on a message.
     *
     * Only one "close" action here, and it is the same one the back button and the
     * Escape key do: right-clicking the background of a thread is a request to get
     * out of it, not a request for anything about a message. A separate menu would be
     * a second set of items competing with the message one for the same gesture.
     */
    const [backgroundMenu, setBackgroundMenu] =
        useState<ContextMenuState | null>(null);

    const onTranscriptContextMenu = useContextMenu((state) => {
        // A bubble already handles its own right-click, and this is the container it
        // sits in: without this check the message menu would open and then be
        // immediately replaced by the background one as the event bubbles up.
        const target = state.target;

        if (target?.closest('[data-message-id]') !== null) {
            return;
        }

        // A touch device has no right button; a long press would fire this same
        // handler and put a menu with no discoverable dismissal over a phone screen
        // where there is no back button to close the thread with. Left to the
        // desktop gesture on purpose — the phone already has its own way out.
        if (window.matchMedia('(pointer: fine)').matches === false) {
            return;
        }

        setBackgroundMenu(state);
    });

    const bottomRef = useRef<HTMLDivElement | null>(null);
    const scrollRef = useRef<HTMLDivElement | null>(null);
    const pendingKey = useRef(0);

    const form = useForm<ChatMessageForm>({ body: '', attachments: [] });
    const { data, setData, post, processing, clearErrors } = form;

    const uuid = room?.uuid ?? null;
    const messages = useMemo(() => thread?.messages ?? [], [thread]);
    const empty = data.body.trim() === '' && data.attachments.length === 0;

    // Private channel: the room's participants only. The server refuses the
    // subscription for anyone else via routes/channels.php.
    useEcho<{ room_uuid: string; message: ChatMessage }>(
        `chat.rooms.${uuid}`,
        '.chat.message.sent',
        (payload) => {
            if (payload.room_uuid !== uuid) {
                return;
            }

            setIncoming((current) =>
                current.some((message) => message.id === payload.message.id)
                    ? current
                    : [...current, payload.message],
            );
        },
        [uuid],
    );

    // Somebody else in the room caught up, so a single tick of ours can become a
    // double one without a reload. Only ever adds: the reload brings the same
    // state in through `read_by_all`.
    useEcho<MessagesReadPayload>(
        `chat.rooms.${uuid}`,
        '.chat.messages.read',
        (payload) => {
            if (payload.room_uuid !== uuid || payload.reader_id === user?.id) {
                return;
            }

            setReadByAll((current) =>
                payload.read_by_all_ids.every((id) => current.includes(id))
                    ? current
                    : [...current, ...payload.read_by_all_ids],
            );
        },
        [uuid, user?.id],
    );

    /*
     * Somebody corrected or removed a message in this room.
     *
     * Applied to the local copy rather than triggering a reload: a person reading
     * the thread should see an account number change under them, not watch the page
     * they were halfway down get thrown away. The one thing that *is* dropped is an
     * open editor whose subject no longer exists.
     */
    useEcho<{ room_uuid: string; message: ChatMessage }>(
        `chat.rooms.${uuid}`,
        '.chat.message.updated',
        (payload) => {
            if (payload.room_uuid !== uuid) {
                return;
            }

            setIncoming((current) =>
                current.some((message) => message.id === payload.message.id)
                    ? current.map((message) =>
                          message.id === payload.message.id
                              ? { ...message, ...payload.message }
                              : message,
                      )
                    : [...current, payload.message],
            );
        },
        [uuid],
    );

    useEcho<{
        room_uuid: string;
        message_id: number;
        for_everyone: boolean;
    }>(
        `chat.rooms.${uuid}`,
        '.chat.message.deleted',
        (payload) => {
            if (payload.room_uuid !== uuid) {
                return;
            }

            setRemoved((current) => new Set(current).add(payload.message_id));
            setEditingId((current) =>
                current === payload.message_id ? null : current,
            );
        },
        [uuid],
    );

    // The server already answered this send, so drop the optimistic copy the
    // reload replaces - otherwise it renders twice.
    useEffect(() => {
        setIncoming([]);
    }, [thread]);

    const visible = useMemo(() => {
        const seen = new Set<number>();
        const merged: ChatMessage[] = [];

        for (const message of [...messages, ...incoming]) {
            if (seen.has(message.id)) {
                continue;
            }

            seen.add(message.id);
            merged.push(message);
        }

        return merged;
    }, [messages, incoming]);

    /*
     * Correcting and removing.
     *
     * The bubble is edited in place rather than in a dialog, because the whole point
     * of an edit is seeing the sentence you are correcting in its surroundings — an
     * account number under a question, not floating in a modal.
     *
     * Both go through a visit that reloads the thread, because the server decides
     * whether the action applied (`can_edit`, the ten-minute window) and a client
     * that assumed success would show a tick on a message the server refused. The
     * redirect reload is the answer coming back; the flash says which way it went.
     */
    function saveEdit(message: ChatMessage, body: string) {
        const trimmed = body.trim();

        if (trimmed === '' || trimmed === message.body) {
            setEditingId(null);

            return;
        }

        router.patch(
            updateMessage.url([roomUuid, message.id]),
            { body: trimmed },
            {
                preserveScroll: true,
                preserveState: true,
                showProgress: false,
                onSuccess: () => setEditingId(null),
            },
        );
    }

    function removeMessage(message: ChatMessage, scope: 'self' | 'everyone') {
        /*
         * `router.delete()` takes no separate data argument — unlike `patch()`, it
         * has no `data` parameter of its own, so the payload rides inside the options
         * object. Passing a third argument would be silently dropped by the router.
         */
        router.delete(destroyMessage.url([roomUuid, message.id]), {
            data: { scope },
            preserveScroll: true,
            preserveState: true,
            showProgress: false,
            // The editor is open on this message and its subject is about to
            // disappear, so leaving it open would strand the cursor in a box whose
            // contents are gone.
            onSuccess: () => setEditingId(null),
        });
    }

    /*
     * A separator whenever the calendar day changes, which is what makes a long
     * thread skimmable. `localDayKey` is used rather than a formatted date so
     * the comparison is on the local day and not on UTC.
     */
    const rows = useMemo<Row[]>(() => {
        const built: Row[] = [];
        let previousDay = '';
        let previousSender: number | null | undefined;
        let previousOwn = false;
        let previousAt: string | null = null;

        for (const message of visible) {
            // Removed over the socket since the last reload, or hidden for this
            // viewer by a page load that has not happened yet.
            if (removed.has(message.id)) {
                continue;
            }
            const day = message.created_at
                ? localDayKey(message.created_at)
                : '';

            if (day !== '' && day !== previousDay) {
                built.push({
                    type: 'day',
                    key: `day-${day}`,
                    label: formatDaySeparator(message.created_at, locale),
                });
                previousDay = day;
                previousSender = null;
            }

            /*
             * Consecutive messages from one person read as a single turn, so the
             * sender's name is printed once at the top of the run and the rest sit
             * tight underneath. A run breaks on a different sender, on the viewer's
             * own message, or after a silence long enough that it is a new thought
             * rather than another line.
             */
            const continues =
                previousSender !== null &&
                previousSender === message.sender_id &&
                isOwnMessage(message, user?.id) === previousOwn &&
                sameRun(previousAt, message.created_at);

            built.push({
                type: 'message',
                key: `m-${message.id}`,
                message,
                own: isOwnMessage(message, user?.id),
                grouped: continues,
            });

            previousSender = message.sender_id;
            previousOwn = isOwnMessage(message, user?.id);
            previousAt = message.created_at;
        }

        return [
            ...built,
            ...pending.map((entry) => ({
                type: 'pending' as const,
                key: entry.key,
                pending: entry,
            })),
        ];
    }, [visible, locale, pending, user?.id]);

    useEffect(() => {
        bottomRef.current?.scrollIntoView({ block: 'end' });
    }, [rows.length]);

    // Keep the newest message in view when the phone keyboard shrinks the thread.
    useEffect(() => {
        const node = scrollRef.current;

        if (node === null) {
            return;
        }

        const observer = new ResizeObserver(() => {
            node.scrollTop = node.scrollHeight;
        });

        observer.observe(node);

        return () => observer.disconnect();
    }, []);

    /**
     * Tell the server which messages have been seen.
     *
     * Reported for everything currently on screen that is not the viewer's own,
     * and only once per message: the endpoint is idempotent but this runs on every
     * message that arrives, so a repeat would be a request per message for no
     * change. The server drops ids from another room and refuses anyone who is
     * not a participant, so a stale tab cannot forge a receipt.
     *
     * Sent as an `async` visit, which sends the request and discards the
     * response: a read receipt must never swap the thread out from under someone
     * reading it. That is also why there is no progress bar - the Inertia
     * indicator for a fire-and-forget POST is a bar flashing across the top of a
     * chat that has not changed.
     */
    const reportRead = useCallback(
        (visible: ChatMessage[], viewerId: number | undefined) => {
            if (viewerId === undefined || uuid === null) {
                return;
            }

            // Oldest first, and capped at what the endpoint accepts. Ids past the
            // cap are deliberately left unreported rather than forgotten: the next
            // message to arrive picks them up, which is the same self-healing the
            // failed-request path relies on.
            const unseen = visible
                .filter((message) => message.sender_id !== viewerId)
                .map((message) => message.id)
                .filter((id) => !reportedRef.current.has(id))
                .slice(0, maxReadIds);

            if (unseen.length === 0) {
                return;
            }

            unseen.forEach((id) => reportedRef.current.add(id));

            router.post(
                readReceipts.url(uuid),
                { message_ids: unseen },
                {
                    async: true,
                    preserveScroll: true,
                    preserveState: true,
                    showProgress: false,
                    onError: () => {
                        // Nothing is wrong with the thread if this fails - the
                        // sender simply keeps a single tick - so the ids go back
                        // into circulation for the next attempt rather than being
                        // remembered as read.
                        unseen.forEach((id) => reportedRef.current.delete(id));
                    },
                },
            );
        },
        [uuid],
    );

    useEffect(() => {
        reportRead(visible, user?.id);
    }, [visible, reportRead, user?.id]);

    // Nothing to read and no room to read it in: the only reason to be here is a
    // draft the member is about to start, and anything else means the workspace
    // has its placeholder panel instead.
    if (room === null || thread === null) {
        return draft === null ? null : (
            <DraftThread
                draft={draft}
                onBack={onBack}
                maxLength={maxLength}
                maxAttachments={maxAttachments}
            />
        );
    }

    /*
     * The room's uuid, read once the null check has run.
     *
     * TypeScript cannot keep `room` narrowed inside a function *declaration* - it
     * has no idea when the closure will be called - so `send()` and `retry()`
     * below would each have to re-check a prop they know is set. Narrowing once
     * into a const holds for the rest of the component.
     */
    const roomUuid = room.uuid;

    const title =
        room.kind === 'support'
            ? t('chat.show.support_title')
            : t(
                  room.kind === 'group'
                      ? 'chat.show.group_title'
                      : 'chat.show.credentials_title',
                  { title: room.auction?.title ?? '' },
              );

    /**
     * The room's own subtitle: who else is in it. The participant list is not
     * rendered as a panel of its own any more, so the header is the only place a
     * member can see who they are talking to.
     */
    const subtitle = [
        ...(user && room.role ? [t(`chat.roles.${room.role}`)] : []),
        /*
         * Counterparties with the role label standing in for a null name - a support
         * thread seen by its own member has no single admin to name. Filtering
         * afterwards is what stops a lone separator appearing when the role label
         * is absent and every name is null.
         */
        ...room.counterparties.map(
            (person) => person.name ?? t(`chat.roles.${person.role}`),
        ),
    ]
        .filter((part) => part !== '')
        .join(' \u00b7 ');

    /**
     * Nothing to send means nothing to press.
     *
     * The server rejects an empty message, and it should never have to: a reader
     * who opens the composer, finds it empty and taps send should not be told
     * off by a validation rule, because the button that offered to break it was
     * never a button they should have been able to press.
     */
    function send(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (processing || empty) {
            return;
        }

        clearErrors();

        const entry: PendingMessage = {
            key: `p-${pendingKey.current++}`,
            body: data.body,
            files: [...data.attachments],
            createdAt: new Date().toISOString(),
            state: 'sending',
        };

        // The bubble goes in before the request leaves, which is the whole point
        // of the pending state: the thread answers immediately and the tick
        // carries what happens next.
        setPending((current) => [...current, entry]);

        /*
         * Submitted *before* the composer is emptied, and that order is the whole
         * reason this works.
         *
         * `useForm` reads its data through a ref that `setData` writes
         * synchronously, so the payload is captured the instant `post()` is
         * called. Clearing first therefore did not clear the form afterwards - it
         * replaced the request's contents with an empty body and no attachments,
         * which the server refused as "a message or an attachment is required".
         * The optimistic bubble caught it as a failure and handed the text back,
         * which is why the first tap never sent anything and only the second one
         * did. Emptying after the submit cannot affect a payload already built.
         */
        post(storeMessage.url(roomUuid), {
            forceFormData: true,
            // The bubble is already on screen, so the top-of-page progress bar
            // would only narrate a request the reader can see the result of.
            showProgress: false,
            onSuccess: () => {
                // The page now carries the stored message; keeping the copy would
                // render it twice.
                setPending((current) =>
                    current.filter((item) => item.key !== entry.key),
                );
            },
            onError: () => {
                setPending((current) =>
                    current.map((item) =>
                        item.key === entry.key
                            ? { ...item, state: 'failed' }
                            : item,
                    ),
                );

                // Give the text back, but never overwrite something typed in the
                // meantime: what is in the composer now is what the reader meant
                // to send, and the failed bubble still holds the original.
                setData((current) => ({
                    body: current.body === '' ? entry.body : current.body,
                    attachments:
                        current.attachments.length === 0
                            ? entry.files
                            : current.attachments,
                }));
            },
        });

        // Emptied last, once the payload has been read.
        setData({ body: '', attachments: [] });
    }

    /**
     * Send a failed bubble again, exactly as it was written the first time.
     *
     * `setData` writes through the form's own ref, so the submit below already
     * sees these values: there is no second submit path and no intermediate
     * render where the composer and the bubble disagree.
     */
    function retry(entry: PendingMessage) {
        setPending((current) =>
            current.map((item) =>
                item.key === entry.key ? { ...item, state: 'sending' } : item,
            ),
        );

        clearErrors();
        setData({ body: entry.body, attachments: entry.files });

        post(storeMessage.url(roomUuid), {
            forceFormData: true,
            showProgress: false,
            onSuccess: () => {
                setPending((current) =>
                    current.filter((item) => item.key !== entry.key),
                );
            },
            onError: () => {
                setPending((current) =>
                    current.map((item) =>
                        item.key === entry.key
                            ? { ...item, state: 'failed' }
                            : item,
                    ),
                );
            },
        });
    }

    function discard(entry: PendingMessage) {
        setPending((current) =>
            current.filter((item) => item.key !== entry.key),
        );
    }

    return (
        <div className="flex min-h-0 min-w-0 flex-1 flex-col">
            {/* Sticky room header: back, who it is, who is in it. */}
            <header className="z-10 flex h-14 shrink-0 items-center gap-2.5 border-b border-border-default bg-neutral-primary-soft px-3 sm:px-4">
                {/*
                 * Only on a phone, where the list is a different screen. On
                 * desktop both halves are already visible and this would close the
                 * thread the reader just opened.
                 */}
                <button
                    type="button"
                    onClick={onBack}
                    className="-ml-2 rounded-full p-2 text-heading hover:bg-neutral-secondary-soft lg:hidden"
                    aria-label={t('chat.show.back')}
                >
                    <ArrowLeftIcon size={20} />
                </button>

                <div className="flex min-w-0 flex-1 items-center gap-2.5">
                    {/*
                     * The same avatar the room list draws, from the same helper: the
                     * conversation's face has to be the same face in both places, or
                     * the list is not actually identifying anything.
                     */}
                    <Avatar
                        name={roomAvatarName(room, t('chat.roles.admin'))}
                        size={36}
                    />

                    <span className="min-w-0">
                        <span className="block truncate font-heading text-sm font-semibold text-heading">
                            {title}
                        </span>
                        {subtitle !== '' ? (
                            <span className="block truncate text-xs text-body-subtle">
                                {subtitle}
                            </span>
                        ) : null}
                    </span>
                </div>

                {room.admits_admin ? (
                    <span
                        className="hidden shrink-0 text-body-subtle sm:block"
                        title={t('chat.show.moderator')}
                    >
                        <ShieldIcon size={16} />
                    </span>
                ) : null}
            </header>

            {/* Older history, kept above the thread rather than inside it. */}
            {thread.older_url ? (
                <div className="shrink-0 border-b border-border-default px-4 py-2 text-center">
                    <button
                        type="button"
                        onClick={() =>
                            router.get(thread.older_url as string, {
                                preserveScroll: true,
                                preserveState: true,
                                only: ['thread'],
                            })
                        }
                        className="text-xs font-medium text-fg-brand underline underline-offset-4 hover:no-underline"
                    >
                        {t('chat.show.older')}
                    </button>
                </div>
            ) : null}

            <ScrollArea
                ref={scrollRef}
                /*
                 * Right-click on the empty space of the thread opens the same menu
                 * as the back button: leave this conversation.
                 *
                 * Bound to the scroll container rather than to each bubble, so the
                 * target is always inside the transcript — a right-click on the
                 * composer is somebody trying to paste, and swallowing that to open
                 * a menu would be actively hostile.
                 */
                onContextMenu={onTranscriptContextMenu}
                className="min-h-0 flex-1 space-y-1 overflow-y-auto overscroll-contain px-3 py-4 sm:px-4"
            >
                {visible.length === 0 && pending.length === 0 ? (
                    <p className="py-10 text-center text-sm text-body-subtle">
                        {t('chat.show.empty_body')}
                    </p>
                ) : null}

                {rows.map((row) => {
                    if (row.type === 'day') {
                        return (
                            <div
                                key={row.key}
                                className="my-4 flex justify-center"
                            >
                                <span className="rounded-full bg-neutral-secondary-soft px-3 py-1 text-xs font-medium text-body-subtle">
                                    {row.label}
                                </span>
                            </div>
                        );
                    }

                    if (row.type === 'pending') {
                        return (
                            <PendingBubble
                                key={row.key}
                                pending={row.pending}
                                locale={locale}
                                onRetry={() => retry(row.pending)}
                                onDiscard={() => discard(row.pending)}
                            />
                        );
                    }

                    return (
                        <MessageBubble
                            key={row.key}
                            message={row.message}
                            own={row.own}
                            grouped={row.grouped}
                            readByAll={readByAll.includes(row.message.id)}
                            editing={editingId === row.message.id}
                            locale={locale}
                            currency={currency}
                            onStartEdit={() => setEditingId(row.message.id)}
                            onCancelEdit={() => setEditingId(null)}
                            onSaveEdit={(body) => saveEdit(row.message, body)}
                            onOpenMenu={(target, state) =>
                                setMenu({ state, message: target })
                            }
                        />
                    );
                })}

                <div ref={bottomRef} />
            </ScrollArea>

            {/* Composer, pinned to the bottom the way a chat app pins it. */}
            <ChatComposer
                form={form}
                onSubmit={send}
                maxLength={maxLength}
                maxAttachments={maxAttachments}
            />

            {/*
             * The right-click menu for a message. Rendered once for the whole thread
             * rather than per bubble: the items depend on the message the menu was
             * opened on, and thirty hidden menus would be thirty sets of listeners.
             */}
            {menu !== null ? (
                <MessageMenu
                    state={menu.state}
                    message={menu.message}
                    onClose={() => setMenu(null)}
                    onEdit={() => {
                        setEditingId(menu.message.id);
                        setMenu(null);
                    }}
                    onDelete={(scope) => removeMessage(menu.message, scope)}
                />
            ) : null}

            {/*
             * Right-click on the thread's empty space. Desktop-only in practice —
             * see the note on the menu's own items — and deliberately one item: the
             * back button on a phone and the Escape key do the same thing, so this is
             * a third way to reach an action that already has two.
             */}
            {backgroundMenu !== null ? (
                <ContextMenu
                    state={backgroundMenu}
                    label={t('chat.actions.menu')}
                    onClose={() => setBackgroundMenu(null)}
                    items={[
                        {
                            key: 'close',
                            label: t('chat.actions.close_room'),
                            onSelect: onBack,
                        },
                    ]}
                />
            ) : null}
        </div>
    );
}

/**
 * One message as a bubble, or — when it is the payment trail — as the centred
 * system line it actually is.
 *
 * Split out because this is where three concerns meet that used to be one long
 * block of markup in the transcript loop: the bubble's shape, the inline editor,
 * and the right-click menu. Inlining it again is what would make the editor a
 * special case in the middle of a `map`.
 */
function MessageBubble({
    message,
    own,
    grouped,
    readByAll,
    editing,
    locale,
    currency,
    onStartEdit,
    onCancelEdit,
    onSaveEdit,
    onOpenMenu,
}: {
    message: ChatMessage;
    own: boolean;
    grouped: boolean;
    readByAll: boolean;
    editing: boolean;
    locale: string;
    currency: string;
    onStartEdit: () => void;
    onCancelEdit: () => void;
    onSaveEdit: (body: string) => void;
    onOpenMenu: (message: ChatMessage, state: ContextMenuState) => void;
}) {
    const { t } = useTranslation();

    /*
     * Only acts when the right-click landed on *this* bubble. Every bubble has the
     * same handler shape, and the one whose id matches the element under the pointer
     * is the one that owns the gesture.
     */
    const onContextMenu = useContextMenu((state) => {
        if (
            Number(state.target?.dataset['messageId'] ?? Number.NaN) ===
            message.id
        ) {
            onOpenMenu(message, state);
        }
    });

    if (isPaymentTrail(message.kind)) {
        return (
            <div className="my-3 flex justify-center px-6">
                <div className="w-full max-w-md rounded-lg border border-border-default bg-neutral-secondary-soft px-3 py-2.5 text-center">
                    <Badge variant={paymentBadge[message.kind]}>
                        {t(`chat.payment.kinds.${message.kind}`)}
                    </Badge>

                    {message.amount !== null ? (
                        <p className="mt-1.5 font-heading text-sm font-bold text-heading">
                            {formatCurrency(message.amount, locale, currency)}
                        </p>
                    ) : null}

                    {/*
              Rendered per message rather than from the room prop, so a payment that
              is still in flight keeps showing the code the winner actually paid even
              after the platform's account changes.
            */}
                    {message.qris_url ? (
                        <div className="mt-2">
                            <PaymentQrisCard
                                qris={{
                                    image_url: message.qris_url,
                                    account_name: null,
                                    account_number: null,
                                }}
                            />
                        </div>
                    ) : null}

                    {message.proof_url ? (
                        <a
                            href={message.proof_url}
                            target="_blank"
                            rel="noreferrer"
                            className="mt-2 block"
                        >
                            <img
                                src={message.proof_url}
                                alt=""
                                className="mx-auto max-h-48 border border-border-default object-contain"
                            />
                        </a>
                    ) : null}

                    <p className="mt-1.5 text-xs whitespace-pre-wrap text-body-subtle">
                        {message.body}
                    </p>
                </div>
            </div>
        );
    }

    return (
        <div
            className={cn(
                'flex',
                own ? 'justify-end' : 'justify-start',
                // A run of messages from one person is one turn: tight underneath,
                // clearly separated from the next one. 4px versus 12px is the whole
                // difference, and it is what stops a thread reading as a wall.
                grouped ? 'mt-1' : 'mt-3',
                !own && 'mb-0.5',
            )}
        >
            <div
                // The id is how the transcript loop's menu handler knows which
                // bubble a right-click landed on, and it is also the id the edit
                // and delete endpoints address.
                data-message-id={message.id}
                onContextMenu={onContextMenu}
                // Double-click is the shortcut people already know from every other
                // chat app, and it is the only way to reach the editor without a
                // right-click. `canEdit` gates it, so somebody else's message does
                // not open an editor the server would refuse.
                onDoubleClick={
                    message.can_edit === true && !editing
                        ? onStartEdit
                        : undefined
                }
                title={
                    message.can_edit === true && !editing
                        ? t('chat.actions.edit')
                        : undefined
                }
                className={cn(
                    // 70% of the panel on desktop, 85% on a phone: the widest a
                    // bubble can get before it stops reading as a message and
                    // starts reading as a paragraph.
                    'max-w-[85%] rounded-xl px-3 py-2 sm:max-w-[70%]',
                    own ? 'bg-brand-softer' : 'bg-neutral-secondary-soft',
                    // Only the corner nearest the sender's side is squared, and only
                    // on a bubble that starts a run: a tail in the middle of a run
                    // points at nothing.
                    grouped
                        ? own
                            ? 'rounded-br-xl'
                            : 'rounded-bl-xl'
                        : own
                          ? 'rounded-br-sm'
                          : 'rounded-bl-sm',
                )}
            >
                {/*
                 * The name heads a run and only a run. Printing it on every bubble
                 * of three consecutive messages is noise; so is printing it once on a
                 * run that continues below, which is why `grouped` decides rather
                 * than "is there an earlier message by this person".
                 */}
                {!own && !grouped ? (
                    <p className="mb-1 text-xs font-semibold text-fg-brand-strong">
                        {message.sender_name ?? t('chat.roles.member')}
                    </p>
                ) : null}

                {message.attachments.length > 0 ? (
                    <AttachmentGrid
                        attachments={message.attachments}
                        locale={locale}
                    />
                ) : null}

                {editing ? (
                    <InlineEditor
                        message={message}
                        onCancel={onCancelEdit}
                        onSave={onSaveEdit}
                    />
                ) : message.body !== '' ? (
                    // 13.5px: a size that fits a name and a sentence without the
                    // transcript turning into a column of long words on a phone.
                    <p className="text-[13.5px] leading-5 whitespace-pre-wrap text-heading">
                        {message.body}
                    </p>
                ) : null}

                {/*
                 * The time rides inside the bubble, right-aligned, and is the only
                 * grey thing in it. On a run it is still printed — a message without
                 * a time cannot be placed — but it sits tight against the text
                 * above it.
                 */}
                <p
                    className={cn(
                        'flex items-center justify-end gap-1 text-[10px] text-body-subtle',
                        grouped ? 'mt-0' : 'mt-1',
                        editing && 'mt-2',
                    )}
                >
                    {formatTime(message.created_at, locale)}

                    {/*
                     * The "edited" mark. Without it a corrected account number is
                     * indistinguishable from one that was always right, and the
                     * whole point of letting somebody fix a typo is that the fix can
                     * be trusted — which needs the reader to know one happened.
                     */}
                    {message.edited_at != null && !editing ? (
                        <span className="italic">
                            · {t('chat.actions.edited')}
                        </span>
                    ) : null}

                    {/* Only the viewer's own messages carry a delivery state: a
                    tick on someone else's would claim something about their
                    send. */}
                    {own ? (
                        <DeliveryTick
                            state={
                                message.read_by_all || readByAll
                                    ? 'read'
                                    : 'sent'
                            }
                        />
                    ) : null}
                </p>
            </div>
        </div>
    );
}

/**
 * Editing a message in place, inside its own bubble.
 *
 * A textarea rather than a one-line input because the mistake being corrected is
 * often a paragraph, and an editor that cannot hold the sentence being corrected
 * is not an editor. Enter saves and Escape cancels, which are the two things a
 * keyboard user expects and which cost nothing to provide here.
 */
function InlineEditor({
    message,
    onCancel,
    onSave,
}: {
    message: ChatMessage;
    onCancel: () => void;
    onSave: (body: string) => void;
}) {
    const { t } = useTranslation();
    const [body, setBody] = useState(message.body);
    const areaRef = useRef<HTMLTextAreaElement | null>(null);

    useEffect(() => {
        const node = areaRef.current;

        if (node === null) {
            return;
        }

        node.focus();
        // The end of the text, not the start: the sentence being corrected is
        // usually at the end of it.
        node.setSelectionRange(node.value.length, node.value.length);
    }, []);

    return (
        <div className="min-w-[16rem]">
            <label htmlFor={`edit-${message.id}`} className="sr-only">
                {t('chat.actions.edit')}
            </label>

            <textarea
                id={`edit-${message.id}`}
                ref={areaRef}
                rows={3}
                value={body}
                onChange={(event) => setBody(event.target.value)}
                onKeyDown={(event) => {
                    if (event.key === 'Enter' && !event.shiftKey) {
                        event.preventDefault();
                        onSave(body);
                    }

                    if (event.key === 'Escape') {
                        event.preventDefault();
                        onCancel();
                    }
                }}
                className="no-scrollbar max-h-40 w-full resize-none overflow-y-auto rounded-lg border border-border-default bg-neutral-primary-soft p-2 text-[13.5px] leading-5 text-heading focus:border-border-brand focus:outline-none"
            />

            <div className="mt-1.5 flex items-center justify-end gap-2">
                <button
                    type="button"
                    onClick={onCancel}
                    className="text-xs font-medium text-body-subtle underline underline-offset-4 hover:no-underline"
                >
                    {t('chat.actions.edit_cancel')}
                </button>

                <button
                    type="button"
                    onClick={() => onSave(body)}
                    className={buttonClasses('brand', 'px-2.5 py-1 text-xs')}
                >
                    {t('chat.actions.edit_save')}
                </button>
            </div>
        </div>
    );
}

/**
 * The menu on one message's right-click.
 *
 * Which items appear is not decided here. `can_edit` and `can_delete_for_all` come
 * from the server, so a menu can never offer an action the endpoint will refuse —
 * and a menu that offers one and then says no is worse than one that never offered
 * it.
 */
function MessageMenu({
    state,
    message,
    onClose,
    onEdit,
    onDelete,
}: {
    state: ContextMenuState;
    message: ChatMessage;
    onClose: () => void;
    onEdit: () => void;
    onDelete: (scope: 'self' | 'everyone') => void;
}) {
    const { t } = useTranslation();

    const items: ContextMenuItem[] = [];

    if (message.can_edit === true) {
        items.push({
            key: 'edit',
            label: t('chat.actions.edit'),
            onSelect: onEdit,
        });
    }

    // Available to anybody in the room: it writes a tombstone in the viewer's own
    // name and removes nothing.
    items.push({
        key: 'delete-self',
        label: t('chat.actions.delete_self'),
        hint: t('chat.actions.delete_self_hint'),
        onSelect: () => onDelete('self'),
    });

    if (message.can_delete_for_all === true) {
        items.push({
            key: 'delete-everyone',
            label: t('chat.actions.delete_everyone'),
            hint: t('chat.actions.delete_everyone_hint'),
            tone: 'danger',
            onSelect: () => onDelete('everyone'),
        });
    }

    return (
        <ContextMenu
            state={state}
            items={items}
            label={t('chat.actions.menu')}
            onClose={onClose}
        />
    );
}

/**
 * The message composer: text, attachments, and the send button.
 *
 * Deliberately its own component so a draft conversation and a real thread send
 * through the same input. The only difference between them is the URL the form
 * posts to, and a second copy of this markup would be a second place for the
 * keyboard shortcut, the attachment rules and the disabled-send logic to drift
 * apart.
 */
function ChatComposer({
    form,
    onSubmit,
    maxLength,
    maxAttachments,
}: {
    form: ChatForm;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
    maxLength: number;
    maxAttachments: number;
}) {
    const { t } = useTranslation();
    const { data, setData, processing, errors, clearErrors } = form;

    const fileRef = useRef<HTMLInputElement | null>(null);
    const bodyRef = useRef<HTMLTextAreaElement | null>(null);

    const previews = useFilePreviews(data.attachments);

    /**
     * Nothing to send means nothing to press.
     *
     * The server rejects an empty message, and it should never have to: a reader
     * who opens the composer, finds it empty and taps send should not be told
     * off by a validation rule, because the button that offered to break it was
     * never a button they should have been able to press.
     */
    const canSend =
        !processing &&
        !(data.body.trim() === '' && data.attachments.length === 0);

    function grow() {
        const node = bodyRef.current;

        if (node === null) {
            return;
        }

        node.style.height = 'auto';
        node.style.height = `${Math.min(node.scrollHeight, maxRows * lineHeight)}px`;
    }

    function pickFiles(event: ChangeEvent<HTMLInputElement>) {
        const picked = Array.from(event.target.files ?? []);

        clearErrors('attachments');

        setData(
            'attachments',
            data.attachments.concat(picked).slice(0, maxAttachments),
        );

        // Reset the input so picking the same file twice in a row still fires a
        // change event, which it would not with the value left in place.
        event.target.value = '';
    }

    function removeFile(index: number) {
        setData(
            'attachments',
            data.attachments.filter((_, position) => position !== index),
        );
    }

    return (
        <form
            onSubmit={onSubmit}
            className="shrink-0 border-t border-border-default bg-neutral-primary-soft px-3 py-2 sm:px-4"
        >
            {data.attachments.length > 0 ? (
                <div className="mb-2 flex flex-wrap gap-2">
                    {data.attachments.map((file, index) => (
                        <div key={`${file.name}-${index}`} className="relative">
                            {previews[index] !== undefined &&
                            file.type.startsWith('image/') ? (
                                <img
                                    src={previews[index]}
                                    alt=""
                                    className="size-16 rounded-lg border border-border-default object-cover"
                                />
                            ) : (
                                <span className="grid size-16 place-items-center rounded-lg border border-border-default bg-neutral-secondary-soft text-body-subtle">
                                    <DocumentIcon size={20} />
                                </span>
                            )}

                            <button
                                type="button"
                                onClick={() => removeFile(index)}
                                className="absolute -top-1.5 -right-1.5 grid size-5 place-items-center rounded-full bg-danger text-xs font-bold text-neutral-primary-soft"
                                aria-label={t('chat.show.remove')}
                            >
                                ×
                            </button>
                        </div>
                    ))}
                </div>
            ) : null}

            <div className="flex items-end gap-2">
                <input
                    ref={fileRef}
                    type="file"
                    accept="image/*,application/pdf"
                    multiple
                    className="sr-only"
                    onChange={pickFiles}
                />

                <button
                    type="button"
                    onClick={() => fileRef.current?.click()}
                    className="grid size-10 shrink-0 place-items-center rounded-full text-body-subtle hover:bg-neutral-secondary-soft"
                    aria-label={t('chat.show.attach')}
                >
                    <PaperclipIcon size={20} />
                </button>

                <label htmlFor="chat-body" className="sr-only">
                    {t('chat.fields.body')}
                </label>
                {/*
                 * Grows with its content up to `maxRows`, then scrolls. The
                 * scrollbar is hidden rather than removed: `no-scrollbar` keeps the
                 * field scrollable by keyboard and by wheel, so a long message is
                 * still reachable — it just does not draw a grey stripe and a pair
                 * of up/down arrows inside a rounded input.
                 */}
                <textarea
                    id="chat-body"
                    ref={bodyRef}
                    rows={1}
                    maxLength={maxLength}
                    value={data.body}
                    placeholder={t('chat.show.placeholder')}
                    onChange={(event) => {
                        setData('body', event.target.value);
                        grow();
                    }}
                    onKeyDown={(event) => {
                        // Enter sends, Shift+Enter breaks the line: the shortcut
                        // people expect from a chat app.
                        if (event.key === 'Enter' && !event.shiftKey) {
                            event.preventDefault();
                            onSubmit(
                                event as unknown as FormEvent<HTMLFormElement>,
                            );
                        }
                    }}
                    className="no-scrollbar max-h-[7.5rem] min-h-10 flex-1 resize-none overflow-y-auto rounded-2xl border border-border-default bg-neutral-primary-soft px-3.5 py-2.5 text-sm leading-6 text-heading placeholder:text-body-subtle focus:border-border-brand focus:outline-none"
                />

                <button
                    type="submit"
                    disabled={!canSend}
                    // `buttonClasses` already styles `disabled:`, so the greyed-out
                    // send button needs nothing added here.
                    className={buttonClasses(
                        'brand',
                        'grid size-10 shrink-0 place-items-center rounded-full p-0',
                    )}
                    aria-label={t('chat.show.send')}
                >
                    <SendIcon size={18} />
                </button>
            </div>

            {/*
             * Reserved whether or not there is a message, so a validation error
             * cannot shove the whole composer up the screen while the reader is
             * looking at it. Only filled in when the server actually said
             * something, so an untouched composer has nothing to report.
             */}
            <InputError
                message={
                    errors.body ?? errors.attachments ?? errors['attachments.0']
                }
            />
        </form>
    );
}

/**
 * A conversation that does not exist yet: header, empty transcript, composer.
 *
 * The first send posts to the endpoint that creates the room, so nothing is
 * written until the member actually says something. Rendered through the same
 * `ChatComposer` as a real thread, which is the point — the input behaves
 * identically whether or not there is a room behind it yet.
 */
function DraftThread({
    onBack,
    maxLength,
    maxAttachments,
}: {
    draft: ChatDraft;
    onBack: () => void;
    maxLength: number;
    maxAttachments: number;
}) {
    const { t } = useTranslation();
    const pendingKey = useRef(0);

    const form = useForm<ChatMessageForm>({ body: '', attachments: [] });
    const { data, setData, post, processing, clearErrors } = form;

    const [pending, setPending] = useState<PendingMessage[]>([]);

    /**
     * Post the first message, which is also what creates the room.
     *
     * The optimistic bubble and the failure handling are the same as a real
     * thread's, because this is the same composer pointed at a different URL. On
     * success the server redirects to `/chat?c={uuid}`, the workspace remounts this
     * panel with the room behind it, and the pending copies are discarded with it.
     */
    function submit(body: string, files: File[]) {
        const entry: PendingMessage = {
            key: `p-${pendingKey.current++}`,
            body,
            files,
            createdAt: new Date().toISOString(),
            state: 'sending',
        };

        clearErrors();
        setPending((current) => [...current, entry]);

        // The form is filled with the message rather than read from the composer,
        // so a retry re-sends what the failed bubble is holding even if the
        // composer has been typed into since.
        setData({ body, attachments: files });

        // Submitted before the composer is emptied: `useForm` snapshots its
        // payload through a ref that `setData` writes synchronously, so clearing
        // first would replace the request body with an empty message.
        post(startSupport.url(), {
            forceFormData: true,
            showProgress: false,
            onError: () => {
                setPending((current) =>
                    current.map((item) =>
                        item.key === entry.key
                            ? { ...item, state: 'failed' }
                            : item,
                    ),
                );

                // Hand the text back, unless something has been typed since.
                setData((current) => ({
                    body: current.body === '' ? body : current.body,
                    attachments:
                        current.attachments.length === 0
                            ? files
                            : current.attachments,
                }));
            },
        });

        setData({ body: '', attachments: [] });
    }

    function send(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (
            processing ||
            (data.body.trim() === '' && data.attachments.length === 0)
        ) {
            return;
        }

        submit(data.body, [...data.attachments]);
    }

    const { locale } = usePage().props;

    return (
        <div className="flex min-h-0 min-w-0 flex-1 flex-col">
            <header className="z-10 flex h-14 shrink-0 items-center gap-2.5 border-b border-border-default bg-neutral-primary-soft px-3 sm:px-4">
                <button
                    type="button"
                    onClick={onBack}
                    className="-ml-2 rounded-full p-2 text-heading hover:bg-neutral-secondary-soft lg:hidden"
                    aria-label={t('chat.show.back')}
                >
                    <ArrowLeftIcon size={20} />
                </button>

                <div className="flex min-w-0 flex-1 items-center gap-2.5">
                    <Avatar name={t('chat.roles.admin')} size={36} />

                    <span className="min-w-0">
                        <span className="block truncate font-heading text-sm font-semibold text-heading">
                            {t('chat.show.support_title')}
                        </span>
                        <span className="block truncate text-xs text-body-subtle">
                            {t('chat.roles.admin')}
                        </span>
                    </span>
                </div>
            </header>

            <ScrollArea className="min-h-0 flex-1 overflow-y-auto px-3 py-4 sm:px-4">
                {pending.map((entry) => (
                    <PendingBubble
                        key={entry.key}
                        pending={entry}
                        locale={locale}
                        onRetry={() => submit(entry.body, entry.files)}
                        onDiscard={() =>
                            setPending((current) =>
                                current.filter(
                                    (item) => item.key !== entry.key,
                                ),
                            )
                        }
                    />
                ))}

                {pending.length === 0 ? (
                    <p className="py-10 text-center text-sm text-body-subtle">
                        {t('chat.draft.intro')}
                    </p>
                ) : null}
            </ScrollArea>

            <ChatComposer
                form={form}
                onSubmit={send}
                maxLength={maxLength}
                maxAttachments={maxAttachments}
            />
        </div>
    );
}

/**
 * Whether a message is the viewer's own.
 *
 * Matched on the author's user id rather than on their role, because roles do
 * not identify people: two members can share one in a room, and in a support
 * thread the member and the admin are the only two parties, so the old role
 * comparison put the admin's messages on the reader's side of a thread the admin
 * is only moderating.
 */
function isOwnMessage(
    message: ChatMessage,
    viewerId: number | undefined,
): boolean {
    return viewerId !== undefined && message.sender_id === viewerId;
}

/**
 * The three states a sent message moves through, in the order they replace one
 * another.
 *
 * On a bubble, in the footer, at 10px, so each state is a word for a screen
 * reader and a glyph for everyone else. A single tick means it is on the server;
 * the double means every other participant has read it, which is why the server
 * decides it: "everybody" is a property of the room, not of the page.
 */
function DeliveryTick({
    state,
}: {
    state: 'pending' | 'sent' | 'read' | 'failed';
}) {
    const { t } = useTranslation();

    const label =
        state === 'pending'
            ? t('chat.show.tick_pending')
            : state === 'read'
              ? t('chat.show.tick_read')
              : state === 'failed'
                ? t('chat.show.failed')
                : t('chat.show.tick_sent');

    const glyph =
        state === 'pending' ? (
            <ClockIcon size={12} />
        ) : state === 'read' ? (
            <CheckCheckIcon size={13} />
        ) : state === 'failed' ? (
            <XCircleIcon size={12} />
        ) : (
            <CheckIcon size={13} />
        );

    return (
        <span
            className={cn(
                'inline-flex items-center',
                state === 'failed' && 'text-fg-danger',
            )}
            title={label}
        >
            {glyph}
            <span className="sr-only">{label}</span>
        </span>
    );
}

/**
 * The viewer's own message, on its way to the server.
 *
 * Rendered from `File` objects rather than the composer's previews, because the
 * composer empties itself the moment the message is sent: the object URLs behind
 * those thumbnails are revoked as soon as the form no longer holds the files, so
 * a pending bubble that borrowed them would render a row of broken images. This
 * one mints its own and drops them when the bubble is replaced by the stored
 * message.
 */
function PendingBubble({
    pending,
    locale,
    onRetry,
    onDiscard,
}: {
    pending: PendingMessage;
    locale: string;
    onRetry: () => void;
    onDiscard: () => void;
}) {
    const { t } = useTranslation();
    const previews = useFilePreviews(pending.files);

    const attachments: ChatMessageAttachment[] = pending.files.map(
        (file, index) => ({
            name: file.name,
            size: file.size,
            url: previews[index] ?? '',
        }),
    );

    return (
        <div className="flex justify-end">
            <div
                className={cn(
                    'max-w-[85%] rounded-2xl rounded-br-sm bg-brand-softer px-3 py-2 opacity-90 sm:max-w-[70%]',
                    pending.state === 'failed' &&
                        'border border-danger bg-danger-soft',
                )}
            >
                {attachments.length > 0 ? (
                    <AttachmentGrid attachments={attachments} locale={locale} />
                ) : null}

                {pending.body !== '' ? (
                    <p className="text-sm whitespace-pre-wrap text-heading">
                        {pending.body}
                    </p>
                ) : null}

                <p className="mt-0.5 flex items-center justify-end gap-1 text-right text-[10px] text-fg-brand-subtle">
                    {formatTime(pending.createdAt, locale)}

                    {pending.state === 'failed' ? (
                        <span className="inline-flex items-center gap-1 text-fg-danger">
                            <DeliveryTick state="failed" />
                        </span>
                    ) : (
                        <DeliveryTick state="pending" />
                    )}
                </p>

                {/*
                 * A message the server refused stays on screen with a way out. The
                 * alternative, dropping the bubble, loses the text: the composer has
                 * already been emptied and is not holding it any more.
                 */}
                {pending.state === 'failed' ? (
                    <div className="mt-2 flex items-center gap-2">
                        <button
                            type="button"
                            onClick={onRetry}
                            className="text-xs font-semibold text-fg-brand underline underline-offset-4 hover:no-underline"
                        >
                            {t('chat.show.retry')}
                        </button>

                        <button
                            type="button"
                            onClick={onDiscard}
                            className="text-xs font-medium text-body-subtle underline underline-offset-4 hover:no-underline"
                        >
                            {t('chat.show.remove')}
                        </button>
                    </div>
                ) : null}
            </div>
        </div>
    );
}

/**
 * The files on one message.
 *
 * Images open full size from the thread; a PDF gets a labelled card, because a
 * PDF has nothing to show as a thumbnail and an unlabelled box is unreadable.
 */
function AttachmentGrid({
    attachments,
    locale,
}: {
    attachments: ChatMessageAttachment[];
    locale: string;
}) {
    return (
        <div
            className={cn(
                'mb-1 grid gap-1',
                attachments.length > 1 && 'grid-cols-2',
            )}
        >
            {attachments.map((attachment) =>
                isPdf(attachment.name) ? (
                    <a
                        key={attachment.url}
                        href={attachment.url}
                        target="_blank"
                        rel="noreferrer"
                        className="flex items-center gap-2.5 rounded-lg bg-neutral-primary-soft px-2.5 py-2"
                    >
                        <span className="grid size-9 shrink-0 place-items-center rounded-md bg-brand-soft text-fg-brand-strong">
                            <DocumentIcon size={18} />
                        </span>
                        <span className="min-w-0">
                            <span className="block truncate text-xs font-medium text-heading">
                                {attachment.name}
                            </span>
                            <span className="block text-[10px] text-body-subtle">
                                {formatFileSize(attachment.size, locale)}
                            </span>
                        </span>
                    </a>
                ) : (
                    <a
                        key={attachment.url}
                        href={attachment.url}
                        target="_blank"
                        rel="noreferrer"
                    >
                        <img
                            src={attachment.url}
                            alt={attachment.name}
                            loading="lazy"
                            className="max-h-56 w-full rounded-lg border border-border-default object-cover"
                        />
                    </a>
                ),
            )}
        </div>
    );
}
