import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import { useEffect, useRef, useState, type FormEvent } from 'react';
import { PaymentPanel } from '@/components/chat/payment-panel';
import { PaymentQrisCard } from '@/components/chat/payment-qris-card';
import { ArrowLeftIcon, SendIcon, ShieldIcon } from '@/components/icons';
import { Badge } from '@/components/ui/badge';
import { buttonClasses } from '@/components/ui/button';
import { InputError } from '@/components/ui/input-error';
import { Textarea } from '@/components/ui/textarea';
import { AppLayout } from '@/layouts/app-layout';
import { formatCurrency, formatDateTime } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { index as chatIndex } from '@/routes/chat';
import { store as storeMessage } from '@/routes/chat/messages';
import type {
    ChatMessage,
    ChatMessageKind,
    ChatThread,
    ChatThreadRoom,
} from '@/types/chat';

type ShowProps = {
    room: ChatThreadRoom;
    thread: ChatThread;
    maxLength: number;
};

const roleVariant: Record<string, 'brand' | 'success' | 'dark'> = {
    member: 'brand',
    seller: 'success',
    winner: 'brand',
    admin: 'dark',
};

/**
 * The payment trail is not chat: it is a record with evidence attached, so it
 * gets its own label and its own layout instead of being styled as a bubble
 * that happens to have an image in it.
 */
function isPaymentTrail(kind: ChatMessageKind): boolean {
    return kind !== 'message';
}

const paymentBadge: Record<ChatMessageKind, 'brand' | 'success' | 'warning'> = {
    message: 'brand',
    payment_request: 'warning',
    payment_proof: 'warning',
    payment_received: 'success',
    transfer_proof: 'warning',
    payment_completed: 'success',
};

export default function ChatShow({ room, thread, maxLength }: ShowProps) {
    const { t } = useTranslation();
    const { auth, currency, locale } = usePage().props;
    const user = auth.user;

    const [incoming, setIncoming] = useState<ChatMessage[]>([]);
    const bottomRef = useRef<HTMLDivElement | null>(null);

    const { data, setData, post, processing, errors, clearErrors } = useForm({
        body: '',
    });

    // Private channel: the room's participants only. The server refuses the
    // subscription for anyone else via routes/channels.php.
    useEcho<{ room_uuid: string; message: ChatMessage }>(
        `chat.rooms.${room.uuid}`,
        '.chat.message.sent',
        (payload) => {
            if (payload.room_uuid !== room.uuid) {
                return;
            }

            setIncoming((current) =>
                current.some((message) => message.id === payload.message.id)
                    ? current
                    : [...current, payload.message],
            );
        },
        [room.uuid],
    );

    // The server already answered this send, so drop the optimistic copy the
    // redirect replaces — otherwise it renders twice.
    useEffect(() => {
        setIncoming([]);
    }, [thread.messages]);

    useEffect(() => {
        bottomRef.current?.scrollIntoView({ block: 'end' });
    }, [incoming.length, thread.messages.length]);

    const seen = new Set<number>();
    const messages: ChatMessage[] = [];
    for (const message of [...thread.messages, ...incoming]) {
        if (seen.has(message.id)) {
            continue;
        }
        seen.add(message.id);
        messages.push(message);
    }

    const title =
        room.kind === 'support'
            ? t('chat.show.support_title')
            : t(
                  room.kind === 'group'
                      ? 'chat.show.group_title'
                      : 'chat.show.credentials_title',
                  { title: room.auction?.title ?? '' },
              );

    function isOwn(message: ChatMessage): boolean {
        return message.sender_role === room.role && room.role !== 'admin';
    }

    function send(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (processing || data.body.trim() === '') {
            return;
        }

        clearErrors('body');
        post(storeMessage.url(room.uuid));
    }

    return (
        <AppLayout title={t('chat.index.title')}>
            <Head title={title} />

            <div className="mx-auto flex w-full max-w-[1152px] flex-col gap-6 px-6 py-10 lg:flex-row">
                <aside className="lg:w-72 lg:shrink-0">
                    <Link
                        href={chatIndex.url()}
                        className="inline-flex items-center gap-2 text-sm font-medium text-fg-brand underline underline-offset-4 hover:no-underline"
                    >
                        <ArrowLeftIcon size={16} />
                        {t('nav.chat')}
                    </Link>

                    <div className="mt-6 border border-border-default bg-neutral-secondary-soft px-5 py-4">
                        <h1 className="font-heading text-base font-bold text-heading">
                            {title}
                        </h1>

                        <p className="mt-3 text-xs font-semibold tracking-wide text-body-subtle uppercase">
                            {t('chat.show.participants')}
                        </p>
                        <ul className="mt-2 space-y-1.5">
                            {user ? (
                                <li className="flex items-center justify-between gap-2 text-sm text-heading">
                                    <span className="truncate">
                                        {user.name}
                                    </span>
                                    {room.role ? (
                                        <Badge
                                            variant={
                                                roleVariant[room.role] ??
                                                'neutral'
                                            }
                                        >
                                            {t(`chat.roles.${room.role}`)}
                                        </Badge>
                                    ) : null}
                                </li>
                            ) : null}
                            {room.counterparties.map((person) => (
                                <li
                                    key={person.name}
                                    className="flex items-center justify-between gap-2 text-sm text-heading"
                                >
                                    <span className="truncate">
                                        {person.name}
                                    </span>
                                    <Badge
                                        variant={
                                            roleVariant[person.role] ??
                                            'neutral'
                                        }
                                    >
                                        {t(`chat.roles.${person.role}`)}
                                    </Badge>
                                </li>
                            ))}
                        </ul>

                        {/*
                         Stated as a fact about the room rather than only as a
                         reassurance: in the credential thread there is no admin
                         at all, so a permanent "Admin moderates this" line would
                         be a lie about exactly the room that matters most.
                     */}
                        <p className="mt-4 flex items-start gap-2 border-t border-border-default pt-3 text-xs text-body-subtle">
                            <ShieldIcon size={14} className="mt-0.5 shrink-0" />
                            {room.admits_admin
                                ? t('chat.show.moderator')
                                : t('chat.show.private_hint')}
                        </p>
                    </div>
                </aside>

                <section className="flex min-w-0 flex-1 flex-col gap-4 border border-border-default bg-neutral-secondary-soft">
                    {room.payment ? (
                        <PaymentPanel
                            roomUuid={room.uuid}
                            payment={room.payment}
                        />
                    ) : null}

                    <div className="flex items-center justify-between border-b border-border-default px-5 py-3">
                        <h2 className="font-heading text-sm font-bold text-heading">
                            {t('chat.show.participants')}
                        </h2>
                        <span className="text-xs text-body-subtle">
                            {thread.total}
                        </span>
                    </div>

                    {thread.older_url ? (
                        <div className="border-b border-border-default px-5 py-2">
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

                    <div className="flex-1 space-y-3 px-5 py-6">
                        {messages.length === 0 ? (
                            <p className="py-10 text-center text-sm text-body-subtle">
                                {t('chat.show.empty_body')}
                            </p>
                        ) : (
                            messages.map((message) => (
                                <div
                                    key={message.id}
                                    className={cn(
                                        'flex',
                                        isOwn(message)
                                            ? 'justify-end'
                                            : 'justify-start',
                                    )}
                                >
                                    <div
                                        className={cn(
                                            'max-w-[80%] border px-3.5 py-2.5',
                                            isOwn(message)
                                                ? 'border-border-brand-subtle bg-brand-softer'
                                                : 'border-border-default bg-neutral-primary-soft',
                                        )}
                                    >
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="text-xs font-semibold text-body-subtle">
                                                {message.sender_name ??
                                                    t('chat.roles.member')}
                                            </span>
                                            <Badge
                                                variant={
                                                    roleVariant[
                                                        message.sender_role
                                                    ] ?? 'neutral'
                                                }
                                            >
                                                {t(
                                                    `chat.roles.${message.sender_role}`,
                                                )}
                                            </Badge>
                                            {isPaymentTrail(message.kind) ? (
                                                <Badge
                                                    variant={
                                                        paymentBadge[
                                                            message.kind
                                                        ]
                                                    }
                                                >
                                                    {t(
                                                        `chat.payment.kinds.${message.kind}`,
                                                    )}
                                                </Badge>
                                            ) : null}
                                            <span className="text-xs text-body-subtle">
                                                {formatDateTime(
                                                    message.created_at,
                                                    locale,
                                                )}
                                            </span>
                                        </div>

                                        {message.amount !== null ? (
                                            <p className="mt-2 font-heading text-base font-bold text-heading">
                                                {formatCurrency(
                                                    message.amount,
                                                    locale,
                                                    currency,
                                                )}
                                            </p>
                                        ) : null}

                                        {message.kind === 'payment_request' &&
                                        room.payment?.qris ? (
                                            <div className="mt-2">
                                                <PaymentQrisCard
                                                    qris={room.payment.qris}
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
                                                    className="max-h-64 w-full border border-border-default object-contain"
                                                />
                                            </a>
                                        ) : null}

                                        <p className="mt-1.5 text-sm whitespace-pre-wrap text-heading">
                                            {message.body}
                                        </p>
                                    </div>
                                </div>
                            ))
                        )}
                        <div ref={bottomRef} />
                    </div>

                    <form
                        onSubmit={send}
                        className="border-t border-border-default px-5 py-4"
                    >
                        <label htmlFor="chat-body" className="sr-only">
                            {t('chat.fields.body')}
                        </label>
                        <Textarea
                            id="chat-body"
                            rows={3}
                            maxLength={maxLength}
                            value={data.body}
                            invalid={Boolean(errors.body)}
                            placeholder={t('chat.show.placeholder')}
                            onChange={(event) =>
                                setData('body', event.target.value)
                            }
                        />

                        <div className="mt-1 flex items-center justify-between gap-3">
                            <InputError message={errors.body} />
                            <button
                                type="submit"
                                disabled={processing}
                                className={buttonClasses(
                                    'brand',
                                    'ml-auto shrink-0 px-4 py-2.5 text-sm',
                                )}
                            >
                                <SendIcon size={16} />
                                {processing
                                    ? t('chat.show.sending')
                                    : t('chat.show.send')}
                            </button>
                        </div>
                    </form>
                </section>
            </div>
        </AppLayout>
    );
}
