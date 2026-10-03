<?php

declare(strict_types=1);

namespace App\Http\Requests\Chat;

use App\Models\ChatRoom;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Marks a thread as caught up for the signed-in viewer.
 *
 * Authorizes in the request rather than the controller, like every other chat
 * form request here: the body of a read receipt is a list of message ids, and
 * "may you mark these read" has to be answered before that list is looked at.
 */
final class MarkMessagesReadRequest extends FormRequest
{
    public function authorize(): bool
    {
        $room = $this->route('chat_room');
        $user = $this->user();

        if (! $room instanceof ChatRoom || $user === null) {
            return false;
        }

        return Gate::forUser($user)->allows('view', $room);
    }

    /**
     * `message_ids` is optional: with no list the whole room is marked, which is
     * what the thread does on open. The list is only sent when the client wants
     * to be specific about which messages it just displayed.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'message_ids' => ['sometimes', 'array', 'max:100'],
            'message_ids.*' => ['integer'],
        ];
    }
}
