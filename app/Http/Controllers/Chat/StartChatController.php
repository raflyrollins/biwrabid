<?php

declare(strict_types=1);

namespace App\Http\Controllers\Chat;

use App\Actions\SendMessage;
use App\Actions\StartChat;
use App\Enums\ChatRoomType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\StartSupportRequest;
use App\Models\Auction;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The threads a conversation needs: the member's support thread with the admin,
 * and the two auction threads, each guarded by its own membership rule.
 *
 * The auction endpoints are separate rather than one endpoint taking a type
 * parameter on purpose: which room a caller may open is a property of the room
 * kind, so letting the client name the kind would hand it the decision. The
 * credential thread's whole point is that the admin is excluded, and that has to
 * come from the route, not from a request field.
 *
 * Only the support thread is created by sending into it. The auction threads are
 * opened by a button that is itself the point of the screen, and their rooms hold
 * the payment trail, so they have to exist before the first message arrives.
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
     * Open the member's support thread by posting the first message into it.
     *
     * The room is created *here*, on the way to storing a message, rather than by
     * the button that opens the composer. A thread nobody has written in is not a
     * conversation: created on the click, it lands in the admin's inbox as an empty
     * row for every member who ever looked at the page, and the admin cannot tell
     * those apart from conversations waiting for a reply.
     *
     * Because `StartSupportRequest` validates first, an empty submit never reaches
     * `forSupport()` — so looking at the composer and leaving costs nothing.
     */
    public function support(
        StartSupportRequest $request,
        StartChat $startChat,
        SendMessage $sendMessage,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();

        $room = $startChat->forSupport($user);

        try {
            $sendMessage(
                $room,
                $user,
                $request->validated('body'),
                $request->file('attachments', []),
            );
        } catch (\Throwable $e) {
            // The message is the conversation. If the send failed there is nothing
            // left worth keeping, and an empty row in the admin's inbox is exactly
            // what this endpoint exists to avoid.
            if (! $room->messages()->exists()) {
                $room->delete();
            }

            throw $e;
        }

        return redirect()->route('chat.index', ['c' => $room->uuid]);
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

        return redirect()->route('chat.index', ['c' => $room->uuid]);
    }
}
