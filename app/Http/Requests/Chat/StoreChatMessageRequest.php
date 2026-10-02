<?php

declare(strict_types=1);

namespace App\Http\Requests\Chat;

use App\Support\ChatConfig;
use Illuminate\Foundation\Http\FormRequest;

final class StoreChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Ownership is settled by the controller through the `reply` ability —
        // it needs the route-bound room, which FormRequest cannot see here.
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'body' => [
                'required',
                'string',
                'max:'.ChatConfig::messageMaxLength(),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.required' => __('ui.chat.errors.body_required'),
            'body.max' => __('ui.chat.errors.body_too_long', [
                'max' => ChatConfig::messageMaxLength(),
            ]),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'body' => __('ui.chat.fields.body'),
        ];
    }
}
