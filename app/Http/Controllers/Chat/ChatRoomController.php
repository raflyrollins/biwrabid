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
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChatRoomController extends Controller
{
    /**
     * The threads the signed-in user takes part in.
     *
     * Admins see every room they moderate, which is every room *except* the
     * seller <-> winner credential handoff; `ChatRoom::admitsAdmin()` excludes
     * it, and the credential rooms are filtered out here so the admin inbox
     * cannot even list them.
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $query = ChatRoom::query()
            ->with(['auction.seller', 'auction.winner', 'initiator', 'latestMessage.sender'])
            ->latest('updated_at');

        if ($user->is_admin) {
            $query->whereIn('type', [
                ChatRoomType::Support->value,
                ChatRoomType::Group->value,
            ]);
        } else {
            $this->scopeToParticipant($query, $user);
        }

        $rooms = $query
            ->paginate(ChatConfig::roomsPerPage())
            ->through(fn (ChatRoom $room): array => ChatPresenter::room($room, $user));

        return Inertia::render('chat/index', [
            'rooms' => $rooms,
            'isAdminInbox' => $user->is_admin,
        ]);
    }

    /**
     * One thread, oldest message first.
     *
     * @param  ChatRoom  $chatRoom  bound by uuid
     */
    public function show(Request $request, ChatRoom $chatRoom): Response
    {
        $this->authorize('view', $chatRoom);

        /** @var User $user */
        $user = $request->user();

        $chatRoom->loadMissing([
            'auction.seller',
            'auction.winner',
            'initiator',
            'latestMessage.sender',
        ]);

        $page = $chatRoom->messages()
            ->with('sender')
            ->latest('id')
            ->paginate(ChatConfig::messagesPerPage());

        // Paged newest-first for the database, reversed for the reader: the
        // thread always renders top-to-bottom in chronological order.
        $messages = array_reverse($page->items());

        return Inertia::render('chat/show', [
            'room' => ChatPresenter::thread($chatRoom, $user),
            'thread' => [
                'messages' => ChatPresenter::messages($messages),
                'total' => $page->total(),
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'older_url' => $page->nextPageUrl(),
                'newer_url' => $page->previousPageUrl(),
            ],
            'maxLength' => ChatConfig::messageMaxLength(),
        ]);
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
