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
     */
    public function store(StoreChatMessageRequest $request, ChatRoom $chatRoom, SendMessage $sendMessage): RedirectResponse
    {
        $this->authorize('reply', $chatRoom);

        /** @var User $user */
        $user = $request->user();

        $sendMessage($chatRoom, $user, $request->validated('body'));

        // No flash here on purpose: the message appears in the thread and the
        // input clears, so a "sent" banner would only interrupt the next one.
        return redirect()->route('chat.show', $chatRoom);
    }
}
