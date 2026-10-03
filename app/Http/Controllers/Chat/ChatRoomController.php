<?php

declare(strict_types=1);

namespace App\Http\Controllers\Chat;

use App\Enums\ChatRoomType;
use App\Http\Controllers\Controller;
use App\Models\ChatRoom;
use App\Models\User;
use App\Support\ChatConfig;
use App\Support\ChatPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ChatRoomController extends Controller
{
    /**
     * The chat workspace: the room list on one side, the selected thread on the
     * other, and one URL for both.
     *
     * The selected room travels as `?c={uuid}` rather than as a path segment, so
     * picking a conversation is a partial visit against the same page instead of a
     * second screen: the admin inbox and a member's own list are the same view
     * with different rooms in it, and a route that renders a *different* component
     * is how those two drift apart again.
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $room = $this->requestedRoom($request, $user);

        return $this->workspace($request, $user, $room, $this->requestedDraft($request, $user, $room));
    }

    /**
     * A link to one conversation, sent to the workspace with the room open.
     *
     * Kept as a route because a payment step, a bookmark and a room started from
     * the storefront all address a conversation rather than the list. It redirects
     * instead of rendering so there is exactly one URL for the screen: `/chat` with
     * `?c=` naming the open room. Two URLs for one page is how the room selection
     * ends up with two behaviours, and it would leave the fire-and-forget read
     * receipt re-rendering the thread on this path, because its response would
     * name a different URL than the one the reader is on.
     *
     * @param  ChatRoom  $chatRoom  bound by uuid
     */
    public function show(Request $request, ChatRoom $chatRoom): RedirectResponse
    {
        $this->authorize('view', $chatRoom);

        return redirect()->route('chat.index', ['c' => $chatRoom->uuid]);
    }

    /**
     * The room named by `?c=`, or null when none was asked for.
     *
     * Authorized here rather than trusted from the query string: `c` is the only
     * place a client names a room, so it is the one place that decides whether
     * this viewer may read it. A member asking for somebody else's thread gets a
     * 403 rather than an empty panel, and an admin asking for the credential
     * handoff gets the same — `ChatRoom::canAccess()` is what refuses it.
     */
    private function requestedRoom(Request $request, User $user): ?ChatRoom
    {
        $uuid = $request->query('c');

        if (! is_string($uuid) || $uuid === '') {
            return null;
        }

        $room = ChatRoom::query()->where('uuid', $uuid)->first();

        abort_if($room === null, 404);

        abort_unless(Gate::forUser($user)->allows('view', $room), 403);

        return $room;
    }

    /**
     * A conversation that has been started but not yet written into.
     *
     * `?new=support` opens the composer's other half without a room: the member
     * sees the thread they are about to start, types, and the first send creates
     * the room and stores the message together. Nothing is written until then, so
     * a member who opens the page, reads it and leaves never appears in the
     * admin's inbox as an empty conversation.
     *
     * Only `support` exists as a draft, and only for a member: an admin has nobody
     * to start a support thread with. A draft is ignored once a room is open, so
     * `?new=support&c={uuid}` is the room and not a half-started thread beside it.
     *
     * @return 'support'|null
     */
    private function requestedDraft(Request $request, User $user, ?ChatRoom $room): ?string
    {
        if ($room !== null || $request->query('new') !== 'support') {
            return null;
        }

        return $user->is_admin ? null : 'support';
    }

    /**
     * The props both entry points render, so the two cannot disagree.
     */
    private function workspace(Request $request, User $user, ?ChatRoom $room, ?string $draft = null): Response
    {
        $room?->loadMissing([
            'auction.seller',
            'auction.winner',
            'initiator',
            'latestMessage.sender',
        ]);

        $rooms = $this->roomsQuery($user)
            ->paginate(ChatConfig::roomsPerPage())
            ->through(fn (ChatRoom $listed): array => ChatPresenter::room($listed, $user));

        return Inertia::render('chat/index', [
            'rooms' => $rooms,
            // Null when nothing is open, which is what puts the "pick a
            // conversation" panel in the right half rather than an empty thread.
            'room' => $room === null ? null : ChatPresenter::room($room, $user),
            'thread' => $room === null ? null : $this->thread($room),
            'draft' => $draft,
            'isAdminInbox' => $user->is_admin,
            'maxLength' => ChatConfig::messageMaxLength(),
            'maxAttachments' => ChatConfig::maxAttachmentsPerMessage(),
        ]);
    }

    /**
     * One page of a room's messages, oldest first, with everything a bubble draws.
     *
     * Newest-first for the database and reversed for the reader, so the thread
     * always renders top-to-bottom in chronological order.
     *
     * @return array<string, mixed>
     */
    private function thread(ChatRoom $room): array
    {
        $page = $room->messages()
            ->with(['sender', 'reads'])
            ->latest('id')
            ->paginate(ChatConfig::messagesPerPage());

        return [
            'messages' => ChatPresenter::messages(array_reverse($page->items()), $room),
            'total' => $page->total(),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'older_url' => $page->nextPageUrl(),
            'newer_url' => $page->previousPageUrl(),
        ];
    }

    /**
     * The rooms this viewer takes part in, with everything the room shape reads.
     *
     * One definition for the list and the sidebar beside the thread: the scoping
     * rules are the interesting part — admins see every room they moderate, which
     * is every room *except* the seller <-> winner credential handoff, since
     * `ChatRoom::admitsAdmin()` excludes it — and they must not be written twice.
     *
     * @return Builder<ChatRoom>
     */
    private function roomsQuery(User $user): Builder
    {
        $query = ChatRoom::query()
            ->with(['auction.seller', 'auction.winner', 'initiator', 'latestMessage.sender'])
            ->latest('updated_at');

        if ($user->is_admin) {
            return $query->whereIn('type', [
                ChatRoomType::Support->value,
                ChatRoomType::Group->value,
            ]);
        }

        $this->scopeToParticipant($query, $user);

        return $query;
    }

    /**
     * @param  Builder<ChatRoom>  $query
     */
    private function scopeToParticipant(Builder $query, User $user): void
    {
        $query->where(function (Builder $rooms) use ($user): void {
            $rooms->where(function (Builder $support) use ($user): void {
                $support
                    ->where('type', ChatRoomType::Support->value)
                    ->where('initiator_id', $user->id);
            })->orWhere(function (Builder $auctionRooms) use ($user): void {
                // Both auction room kinds. The seller and the winner are derived
                // from the auction, so the room is visible when either party is
                // the viewer.
                $auctionRooms
                    ->whereIn('type', [
                        ChatRoomType::Group->value,
                        ChatRoomType::Auction->value,
                    ])
                    ->whereHas('auction', function (Builder $auction) use ($user): void {
                        $auction
                            ->where('seller_id', $user->id)
                            ->orWhere('winner_id', $user->id);
                    });
            });
        });
    }
}
