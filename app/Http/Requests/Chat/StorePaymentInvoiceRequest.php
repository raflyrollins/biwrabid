<?php

declare(strict_types=1);

namespace App\Http\Requests\Chat;

use App\Models\ChatRoom;
use App\Support\ChatConfig;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * The admin's receiving QRIS, uploaded into the group thread.
 *
 * A QR code is the one file the platform cannot ship on its own: it belongs to
 * the account the admin actually wants paid, and nothing here can generate a code
 * a payment app would accept. So it is required at the moment the invoice is
 * raised rather than configured up front — which also means an unconfigured
 * deployment is not stuck behind a button that can never work.
 */
final class StorePaymentInvoiceRequest extends FormRequest
{
    /**
     * Authorize here rather than in the controller.
     *
     * `authorize()` runs before `rules()`, so an outsider is turned away with a
     * 403 instead of a validation redirect that would reveal whether the field
     * was filled in. Left in the controller it would run after validation, which
     * makes "who may invoice" depend on whether a file happened to be attached.
     */
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
        return [
            'qris' => [
                'required',
                'file',
                'image',
                'mimes:'.implode(',', ChatConfig::proofMimes()),
                'max:'.ChatConfig::maxProofSizeKb(),
                Rule::file()
                    ->types(ChatConfig::proofMimes())
                    ->max(ChatConfig::maxProofSizeKb()),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'qris.required' => __('ui.chat.payment.errors.qris_required'),
            'qris.image' => __('ui.chat.payment.errors.qris_image'),
            'qris.mimes' => __('ui.chat.payment.errors.qris_image'),
            'qris.max' => __('ui.chat.payment.errors.qris_size', [
                'max' => ChatConfig::maxProofSizeKb(),
            ]),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'qris' => __('ui.chat.payment.fields.qris'),
        ];
    }
}
