import { useCallback, useRef, useState } from 'react';
import type { ChatRoom } from '@/types/chat';

/**
 * Which rooms have something unread in them.
 *
 * Defined by when this viewer last *opened* each room, compared against the newest
 * message the list already carries. Both numbers are already on screen — opening a
 * room is the only thing that marks it read — so an unread badge costs no query and
 * no column, and it cannot drift from the timestamps the list renders.
 *
 * A room the viewer has never opened has no entry, so it is not treated as unread:
 * the first visit is a first visit, not a backlog.
 */
export function useUnreadRooms(
    rooms: ChatRoom[],
    activeUuid: string | null,
): Record<string, boolean> {
    const [openedAt, setOpenedAt] = useState<Record<string, string>>({});
    const openedAtRef = useRef(openedAt);

    /*
     * Mark the open room as read. Written through a ref as well as state so the
     * effect below cannot run twice with a stale map — React batches the state
     * update, and a render triggered by the poll in between would otherwise compare
     * against the previous value and flag the room unread.
     */
    const markOpened = useCallback((uuid: string | null) => {
        if (uuid === null) {
            return;
        }

        const now = new Date().toISOString();

        openedAtRef.current = { ...openedAtRef.current, [uuid]: now };
        setOpenedAt(openedAtRef.current);
    }, []);

    // Keyed on the open room, so switching conversations marks the new one read.
    const lastMarked = useRef<string | null>(null);

    if (lastMarked.current !== activeUuid) {
        lastMarked.current = activeUuid;
        markOpened(activeUuid);
    }

    const unread: Record<string, boolean> = {};

    for (const room of rooms) {
        const since = openedAtRef.current[room.uuid];

        if (since === undefined || room.updated_at === null) {
            continue;
        }

        if (new Date(room.updated_at).getTime() > new Date(since).getTime()) {
            unread[room.uuid] = true;
        }
    }

    return unread;
}
