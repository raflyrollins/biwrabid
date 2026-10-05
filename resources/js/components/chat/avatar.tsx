import type { ChatRoom } from '@/types/chat';
import { cn } from '@/lib/utils';

/**
 * Initials for a name: the first letter of the first two words.
 *
 * One implementation because two places need it — the room list and the thread
 * header — and an avatar that says "AM" above a header that says "AM" is the whole
 * point. Falls back to `?` rather than an empty circle for a name that has no
 * letters in it, which is a deleted account.
 */
export function initials(name: string): string {
    const letters = name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');

    return letters === '' ? '?' : letters;
}

/**
 * Soft avatar backgrounds, picked by a hash of the name.
 *
 * Five muted fills and one ink colour. A palette big enough to matter would turn a
 * list of conversations into a swatch chart, and the accent is already spoken for
 * by the brand blue — an avatar is here to tell two people apart, not to be looked
 * at.
 *
 * Hashing the *name* rather than the role is what keeps it stable: the same person
 * is the same colour in the list, in the header and on every screen, and a member
 * who joins two auctions does not turn into two different people.
 */
const avatarTones = [
    'bg-brand-softer text-fg-brand-strong',
    'bg-success-soft text-fg-success-strong',
    'bg-warning-soft text-fg-warning-strong',
    'bg-neutral-tertiary text-heading',
    'bg-neutral-secondary-medium text-heading',
] as const;

/**
 * The tone for a name: a stable index derived from the characters themselves.
 *
 * A rolling hash rather than `charCodeAt` on one character, so "Andi" and "Budi"
 * are very unlikely to collide the way first-letter bucketing makes them.
 */
export function avatarTone(name: string): string {
    let hash = 0;

    for (let index = 0; index < name.length; index++) {
        hash = (hash << 5) - hash + name.charCodeAt(index);
        hash |= 0;
    }

    return avatarTones[Math.abs(hash) % avatarTones.length];
}

/**
 * A circular initial avatar: the same person, the same colours, wherever they
 * appear.
 *
 * `size` is the diameter in pixels and the text scales with it, so the header can
 * ask for 36 and a dense list row for 32 without the initials ending up visually
 * different in two places.
 */
export function Avatar({
    name,
    size = 32,
    className,
}: {
    name: string;
    size?: number;
    className?: string;
}) {
    return (
        <span
            aria-hidden="true"
            style={{ width: `${size}px`, height: `${size}px` }}
            className={cn(
                'inline-grid shrink-0 place-items-center rounded-full font-heading font-bold select-none',
                avatarTone(name),
                className,
            )}
        >
            <span style={{ fontSize: `${Math.round(size * 0.38)}px` }}>
                {initials(name)}
            </span>
        </span>
    );
}

/**
 * The name a room's avatar should show.
 *
 * Derived from the counterparties rather than the room title, because the person
 * you are talking to is what identifies a conversation in a list. The order is
 * deliberate: the thread header joins these names with a middot, so taking the
 * first here matches what the header shows beside it.
 *
 * Falls back to the room title for an auction room with no clear single
 * counterpart, and to the generic chat label when there is nothing at all.
 */
export function roomAvatarName(room: ChatRoom, fallbackLabel: string): string {
    const names = room.counterparties
        .map((person) => person.name)
        .filter((name): name is string => name !== null && name !== '');

    if (names.length > 0) {
        return names[0];
    }

    return room.auction?.title ?? fallbackLabel;
}
