<?php

declare(strict_types=1);

namespace App\Http\Requests\Chat;

use App\Support\ChatConfig;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A transfer receipt, uploaded into the group thread.
 *
 * One request for both directions — the winner's payment to the admin and the
 * admin's payout to the seller — because they are the same file type under the
 * same limits and validating them separately would only let the two drift.
 */
final class StorePaymentProofRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Which direction of the payment this upload belongs to is a business
        // rule, not something a FormRequest can see: it depends on the viewer's
        // role in the room and the payment's current status. The controller
        // settles membership through the `reply` ability and the action settles
        // the step.
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'proof' => [
                'required',
                'file',
                'image',
                'mimes:'.implode(',', ChatConfig::proofMimes()),
                'max:'.ChatConfig::maxProofSizeKb(),
                // `mimes` checks the extension and `mimes` on the *guessed*
                // mime; `mimetypes` closes the gap where a renamed `.php` sails
                // past the extension check on a misconfigured server.
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
            'proof.required' => __('ui.chat.payment.errors.proof_required'),
            'proof.image' => __('ui.chat.payment.errors.proof_image'),
            'proof.mimes' => __('ui.chat.payment.errors.proof_image'),
            'proof.max' => __('ui.chat.payment.errors.proof_size', [
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
            'proof' => __('ui.chat.payment.fields.proof'),
        ];
    }
}
