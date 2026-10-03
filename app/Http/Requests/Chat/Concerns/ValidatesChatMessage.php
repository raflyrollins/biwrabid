<?php

declare(strict_types=1);

namespace App\Http\Requests\Chat\Concerns;

use App\Support\ChatConfig;
use Illuminate\Validation\Rule;

/**
 * The rules every "post a message" endpoint shares.
 *
 * Two endpoints write messages: one into a room that already exists, and one
 * that creates the member's support thread as it posts the first message. The
 * caption-and-files rules are the same in both, and a second copy would be a
 * second answer to "may this be sent with no words at all" — the kind of drift
 * that only shows up as a support ticket about one of the two screens.
 *
 * The two requests keep their own `authorize()`: who may post is a question about
 * the room, and only one of them has a room to ask about.
 */
trait ValidatesChatMessage
{
    /**
     * @return array<string, list<mixed>>
     */
    protected function messageRules(): array
    {
        $mimes = implode(',', ChatConfig::attachmentMimes());
        $maxKb = ChatConfig::maxAttachmentSizeKb();

        return [
            /*
             * A photo with no caption is the common case in a room like this, so
             * the body is only required when nothing came along with it.
             *
             * `nullable` is not optional here. Laravel's `ConvertEmptyStringsToNull`
             * turns a caption-less `body: ''` into `null` before validation ever
             * runs, and a `string` rule without `nullable` rejects `null` — so an
             * attachment-only send failed with "the message must be a string"
             * instead of passing `required_without`. `SendMessage` casts the null
             * back to `''`, which is what the column stores anyway.
             */
            'body' => [
                'nullable',
                'required_without:attachments',
                'string',
                'max:'.ChatConfig::messageMaxLength(),
            ],

            'attachments' => ['sometimes', 'array', 'max:'.ChatConfig::maxAttachmentsPerMessage()],
            'attachments.*' => [
                'file',
                'mimes:'.$mimes,
                'max:'.$maxKb,
                Rule::file()
                    ->types(ChatConfig::attachmentMimes())
                    ->max($maxKb),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messageMessages(): array
    {
        return [
            'body.required' => __('ui.chat.errors.body_required'),
            'body.max' => __('ui.chat.errors.body_too_long', [
                'max' => ChatConfig::messageMaxLength(),
            ]),
            'attachments.max' => __('ui.chat.errors.attachments_too_many', [
                'max' => ChatConfig::maxAttachmentsPerMessage(),
            ]),
            'attachments.*.mimes' => __('ui.chat.errors.attachment_type'),
            'attachments.*.max' => __('ui.chat.errors.attachment_too_large', [
                'max' => ChatConfig::maxAttachmentSizeKb(),
            ]),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messageAttributes(): array
    {
        return [
            'body' => __('ui.chat.fields.body'),
            'attachments' => __('ui.chat.fields.attachments'),
            'attachments.*' => __('ui.chat.fields.attachment'),
        ];
    }
}
