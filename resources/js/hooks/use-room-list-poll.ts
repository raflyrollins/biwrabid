import { router } from '@inertiajs/react';
import { useEffect } from 'react';

/**
 * Keep the room list honest while the viewer is looking at it.
 *
 * The thread itself is realtime: `ChatThreadPanel` subscribes to the open room's
 * channel, so a message that arrives while you are reading appears without a
 * reload. The *list* had no such subscription, which is why an admin sitting on
 * their inbox with no conversation open saw nothing when a member started a thread
 * — the message landed in the database and in the member's screen, and the inbox sat
 * unchanged until refreshed by hand. For the screen whose whole job is "what is
 * waiting for me", that silence reads as a bug.
 *
 * `router.poll` rather than a hand-rolled `setInterval`: it is Inertia's own
 * facility for this, it pauses itself when the tab is hidden or the window is
 * blurred, and it cancels in flight requests on teardown — all three of which a
 * timer in an effect has to reimplement, badly, before it can be trusted.
 *
 * It fetches only the `rooms` prop, and `preserveErrors`/`preserveScroll` come from
 * the visit itself: a poll must never clear a validation message or move the list
 * under the reader's cursor. Paused while a conversation is open, because Echo
 * already covers that room and a poll would fight it.
 */
export function useRoomListPoll(
    activeUuid: string | null,
    intervalMs = 10_000,
): void {
    useEffect(() => {
        if (activeUuid !== null) {
            return;
        }

        const poller = router.poll(
            intervalMs,
            {
                only: ['rooms'],
                showProgress: false,
            },
            // `autoStart` on, `keepAlive` off: only poll while the tab is in front
            // of somebody.
            { autoStart: true, keepAlive: false },
        );

        return () => {
            poller.stop();
        };
    }, [activeUuid, intervalMs]);
}
