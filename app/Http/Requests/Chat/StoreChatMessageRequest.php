<?php

declare(strict_types=1);

namespace App\Http\Requests\Chat;

use App\Http\Requests\Chat\Concerns\ValidatesChatMessage;
use App\Models\ChatRoom;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Authorizes here rather than in the controller.
 *
 * `authorize()` runs before `rules()`, so an outsider is turned away with a
 * 403 rather than a validation redirect. That matters more now that `body` is
 * optional: left in the controller, "may you post here" would depend on whether
 * the sender happened to attach a file.
 */
final class StoreChatMessageRequest extends FormRequest
{
    use ValidatesChatMessage;

    public function authorize(): bool
    {
        $room = $this->route('chat_room');
        $user = $this->user();

        if (! $room instanceof ChatRoom || $user === null) {
            return false;
        }

        return Gate::forUser($user)->allows('reply', $room);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return $this->messageRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->messageMessages();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->messageAttributes();
    }
}
