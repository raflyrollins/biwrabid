<?php

declare(strict_types=1);

namespace App\Http\Controllers\Chat;

use App\Actions\StartChat;
use App\Enums\ChatRoomType;
use App\Http\Controllers\Controller;
use App\Models\Auction;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Opens the two auction threads, each guarded by its own membership rule.
 *
 * The two endpoints are separate rather than one endpoint taking a type
 * parameter on purpose: which room a caller may open is a property of the room
 * kind, so letting the client name the kind would hand it the decision. The
 * credential thread's whole point is that the admin is excluded, and that has to
 * come from the route, not from a request field.
 */
class StartChatController extends Controller
{
    /**
     * Open — or reopen — the group thread: seller, winner and admin together.
     *
     * Where payment is verified, transfer receipts are exchanged and disputes
     * are settled.
     */
    public function group(Request $request, Auction $auction, StartChat $startChat): RedirectResponse
    {
        return $this->openAuctionRoom($request, $auction, $startChat, ChatRoomType::Group);
    }

    /**
     * Open — or reopen — the private credential handoff.
     *
     * The membership check runs *before* the room is created so a stranger
     * cannot open a thread on an auction they have no part in. It also excludes
     * the admin: `StartChat::auctionRoom()` returns null with no winner, so the
     * thread is not opened until there is somebody to hand the account to.
     */
    public function credentials(Request $request, Auction $auction, StartChat $startChat): RedirectResponse
    {
        return $this->openAuctionRoom($request, $auction, $startChat, ChatRoomType::Auction);
    }

    /**
     * Open — or reopen — the member's own support thread with the admin.
     */
    public function support(Request $request, StartChat $startChat): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $room = $startChat->forSupport($user);

        return redirect()->route('chat.show', $room);
    }

    /**
     * Authorise against the auction, then open the thread of the given kind.
     *
     * `Auction::chatRoleFor()` is the single source of truth for who is in which
     * thread, so this route and the `can.*` props the storefront renders cannot
     * drift apart.
     */
    private function openAuctionRoom(
        Request $request,
        Auction $auction,
        StartChat $startChat,
        ChatRoomType $type,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();

        if (! $auction->hasChatParticipant($type, $user)) {
            return redirect()
                ->route('auctions.show', $auction)
                ->with('error', __('ui.chat.errors.no_chat'));
        }

        $room = $type === ChatRoomType::Group
            ? $startChat->forAuctionGroup($auction)
            : $startChat->forAuctionCredentials($auction);

        if ($room === null) {
            return redirect()
                ->route('auctions.show', $auction)
                ->with('error', __('ui.chat.errors.no_winner'));
        }

        return redirect()->route('chat.show', $room);
    }
}
