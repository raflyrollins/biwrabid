<?php

declare(strict_types=1);

namespace App\Http\Controllers\Chat;

use App\Actions\ManageMessage;
use App\Http\Controllers\Controller;
use App\Models\ChatRoom;
use App\Models\User;
use App\Support\ChatConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Correcting and removing one message.
 *
 * Separate endpoints rather than one `POST` with a `mode` field, for the reason the
 * two auction chat endpoints are separate: which thing a caller may touch is a
 * property of the request's *shape*, and a client that can name the mode is a
 * client that can name the mode it is not allowed to use. Here the path already
 * says "this message, this room", and the actor on the message decides the rest.
 */
class MessageActionController extends Controller
{
    /**
     * Correct the text of a message the viewer wrote.
     *
     * Redirects to the room rather than the list, so the thread stays open where it
     * was — the same rule every other chat redirect follows.
     */
    public function update(
        Request $request,
        ChatRoom $chatRoom,
        int $message,
        ManageMessage $manage,
    ): RedirectResponse {
        $this->authorize('modify', $chatRoom);

        $validated = $request->validate([
            'body' => [
                'required',
                'string',
                'max:'.ChatConfig::messageMaxLength(),
            ],
        ]);

        /** @var User $user */
        $user = $request->user();

        $edited = $manage->edit(
            $chatRoom,
            $chatRoom->messages()->findOrFail($message),
            $user,
            $validated['body'],
        );

        return redirect()
            ->route('chat.index', ['c' => $chatRoom->uuid])
            ->with(
                'status',
                $edited === null
                    ? __('ui.chat.feedback.edit_refused')
                    : __('ui.chat.feedback.edited'),
            );
    }

    /**
     * Take a message out of the conversation.
     *
     * `scope` is the only client-named decision here, and it is safe precisely
     * because it cannot grant anything: "everyone" still goes through the author's
     * own window check in `ManageMessage`, so naming the stricter scope as "me"
     * only ever hides something from the person asking.
     */
    public function destroy(
        Request $request,
        ChatRoom $chatRoom,
        int $message,
        ManageMessage $manage,
    ): RedirectResponse {
        $this->authorize('modify', $chatRoom);

        $validated = $request->validate([
            'scope' => ['required', Rule::in(['self', 'everyone'])],
        ]);

        /** @var User $user */
        $user = $request->user();

        $target = $chatRoom->messages()->findOrFail($message);

        $everyone = $validated['scope'] === 'everyone';

        $removed = $everyone
            ? $manage->deleteForAll($chatRoom, $target, $user)
            : $manage->deleteForSelf($chatRoom, $target, $user);

        return redirect()
            ->route('chat.index', ['c' => $chatRoom->uuid])
            ->with(
                'status',
                $removed
                    ? ($everyone
                        ? __('ui.chat.feedback.deleted_everyone')
                        : __('ui.chat.feedback.deleted_self'))
                    : __('ui.chat.feedback.delete_refused'),
            );
    }
}
