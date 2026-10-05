<?php

declare(strict_types=1);

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Models\ChatRoom;
use App\Models\ChatRoomDismissal;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * "Close this chat for me" — on a whole conversation.
 *
 * A tombstone, not a delete: the room keeps existing for the other participants and
 * for the payment trail behind it, which is the record a dispute is settled from.
 * The endpoint writes one row and returns, and the room list leaves the room out
 * for this person from then on.
 *
 * Idempotent, because the same click can arrive twice — the menu item and the Escape
 * key are two ways to ask for the same thing, and a person leaning on Escape will
 * ask more than once.
 */
class RoomDismissalController extends Controller
{
    public function store(Request $request, ChatRoom $chatRoom): RedirectResponse
    {
        $this->authorize('view', $chatRoom);

        /** @var User $user */
        $user = $request->user();

        ChatRoomDismissal::query()->firstOrCreate([
            'chat_room_id' => $chatRoom->id,
            'user_id' => $user->id,
        ]);

        // Back to the list, because closing the chat is what leaves it.
        return redirect()
            ->route('chat.index')
            ->with('status', __('ui.chat.feedback.room_closed'));
    }
}
