<?php

declare(strict_types=1);

namespace App\Http\Controllers\Chat;

use App\Actions\SendMessage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\StoreChatMessageRequest;
use App\Models\ChatRoom;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class MessageController extends Controller
{
    /**
     * Post a message into a thread the sender actually belongs to.
     *
     * The body may be `null` as long as files came along — a screenshot of an
     * account's stats rarely needs a caption. Access is settled by
     * `StoreChatMessageRequest::authorize()`, which sees the room before any
     * validation runs.
     */
    public function store(StoreChatMessageRequest $request, ChatRoom $chatRoom, SendMessage $sendMessage): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $sendMessage(
            $chatRoom,
            $user,
            $request->validated('body'),
            $request->file('attachments', []),
        );

        // No flash here on purpose: the message appears in the thread and the
        // input clears, so a "sent" banner would only interrupt the next one.
        //
        // Back to the workspace with the room named, not to `/chat/{uuid}`: the
        // list stays on screen either way, but only this keeps the URL in the one
        // shape the sidebar itself navigates to.
        return redirect()->route('chat.index', ['c' => $chatRoom->uuid]);
    }
}
