<?php

declare(strict_types=1);

namespace App\Http\Requests\Chat;

use App\Http\Requests\Chat\Concerns\ValidatesChatMessage;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The first message of a member's support conversation.
 *
 * This request exists so the thread is created *by sending into it* rather than
 * by pressing a button: a room with no messages is not a conversation, it is a
 * row that shows up in the admin's inbox as an empty thread somebody never
 * wrote in. Validating here, before `StartChat::forSupport()` runs, is what
 * guarantees that — an empty submit fails validation and no room is written at
 * all.
 */
final class StartSupportRequest extends FormRequest
{
    use ValidatesChatMessage;

    /**
     * Only a member opens a support thread.
     *
     * The admin is the *other* side of it, so there is nobody for an admin to
     * open one with; a support room with two admins in it would be a room whose
     * participants cannot be told apart.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ! $user->is_admin;
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
