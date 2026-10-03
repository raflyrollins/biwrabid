<?php

declare(strict_types=1);

namespace App\Http\Controllers\Chat;

use App\Actions\CoordinatePayment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\StorePaymentInvoiceRequest;
use App\Http\Requests\Chat\StorePaymentProofRequest;
use App\Models\ChatRoom;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The five payment steps, all inside the auction's group thread.
 *
 * Every handler follows the same shape: authorise `reply` (is this user in the
 * room at all), hand the work to `CoordinatePayment` (is this the right actor
 * for this step, in the right state), and redirect back to where the step was
 * driven from. The action returning null is a business rejection, not an error —
 * the panel has already moved on — so it flashes the reason rather than throwing.
 *
 * There is no flash on success for the upload steps, matching `MessageController`:
 * the uploaded receipt appears in the thread immediately.
 */
class PaymentController extends Controller
{
    /**
     * Step 1: the admin uploads their QRIS; the amount is worked out by the action.
     */
    public function storeRequest(StorePaymentInvoiceRequest $request, ChatRoom $chatRoom, CoordinatePayment $payment): RedirectResponse
    {
        // Membership was already settled by the form request, which authorizes
        // before it validates; `CoordinatePayment` settles the payment step.
        /** @var User $user */
        $user = $request->user();

        if ($payment->sendRequest($chatRoom, $user, $request->file('qris')) === null) {
            return $this->reject($chatRoom);
        }

        return $this->settled($chatRoom, __('ui.chat.payment.flash.request_sent'));
    }

    /**
     * Step 2: the winner uploads their transfer receipt.
     */
    public function storeProof(StorePaymentProofRequest $request, ChatRoom $chatRoom, CoordinatePayment $payment): RedirectResponse
    {
        $this->authorize('reply', $chatRoom);

        /** @var User $user */
        $user = $request->user();

        $message = $payment->recordPaymentProof(
            $chatRoom,
            $user,
            $request->file('proof'),
        );

        if ($message === null) {
            return $this->reject($chatRoom);
        }

        return $this->settled($chatRoom);
    }

    /**
     * Step 3: the admin confirms the winner's transfer reached their account.
     */
    public function markReceived(Request $request, ChatRoom $chatRoom, CoordinatePayment $payment): RedirectResponse
    {
        $this->authorize('reply', $chatRoom);

        /** @var User $user */
        $user = $request->user();

        if ($payment->markReceived($chatRoom, $user) === null) {
            return $this->reject($chatRoom);
        }

        return $this->settled($chatRoom, __('ui.chat.payment.flash.received'));
    }

    /**
     * Step 4: the admin uploads proof of paying the seller out.
     */
    public function storeTransferProof(StorePaymentProofRequest $request, ChatRoom $chatRoom, CoordinatePayment $payment): RedirectResponse
    {
        $this->authorize('reply', $chatRoom);

        /** @var User $user */
        $user = $request->user();

        $message = $payment->recordTransferProof(
            $chatRoom,
            $user,
            $request->file('proof'),
        );

        if ($message === null) {
            return $this->reject($chatRoom);
        }

        return $this->settled($chatRoom);
    }

    /**
     * Step 5: the seller confirms the payout landed, which marks the auction paid.
     */
    public function confirm(Request $request, ChatRoom $chatRoom, CoordinatePayment $payment): RedirectResponse
    {
        $this->authorize('reply', $chatRoom);

        /** @var User $user */
        $user = $request->user();

        if ($payment->confirmPayment($chatRoom, $user) === null) {
            return $this->reject($chatRoom);
        }

        // The one payment action worth a flash: it is an auction lifecycle
        // outcome, the seller is on a different page than the winner, and nobody
        // else would otherwise learn the money landed.
        return $this->settled($chatRoom, __('ui.chat.payment.flash.completed'));
    }

    /**
     * The step went through; send the actor back to where they were standing.
     *
     * The steps are driven from the auction page now that the payment panel
     * lives there, so redirecting to the thread would throw the actor out of the
     * screen they acted on. The thread stays one click away, and the receipt the
     * step just wrote is waiting in it.
     */
    private function settled(ChatRoom $chatRoom, ?string $status = null): RedirectResponse
    {
        $redirect = $chatRoom->auction === null
            ? redirect()->route('chat.index', ['c' => $chatRoom->uuid])
            : redirect()->route('auctions.show', $chatRoom->auction);

        return $status === null ? $redirect : $redirect->with('status', $status);
    }

    /**
     * The step did not apply: wrong actor, wrong status, or no QRIS configured.
     */
    private function reject(ChatRoom $chatRoom): RedirectResponse
    {
        return $this->settled($chatRoom)->with('error', __('ui.chat.payment.errors.unavailable'));
    }
}
