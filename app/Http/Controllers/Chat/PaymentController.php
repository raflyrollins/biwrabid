<?php

declare(strict_types=1);

namespace App\Http\Controllers\Chat;

use App\Actions\CoordinatePayment;
use App\Http\Controllers\Controller;
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
 * for this step, in the right state), and redirect back to the thread. The
 * action returning null is a business rejection, not an error — the panel has
 * already moved on — so it flashes the reason rather than throwing.
 *
 * There is no flash on success for the upload steps, matching `MessageController`:
 * the uploaded receipt appears in the thread immediately.
 */
class PaymentController extends Controller
{
    /**
     * Step 1: the admin posts the static QRIS and the invoiced amount.
     */
    public function storeRequest(Request $request, ChatRoom $chatRoom, CoordinatePayment $payment): RedirectResponse
    {
        $this->authorize('reply', $chatRoom);

        /** @var User $user */
        $user = $request->user();

        if ($payment->sendRequest($chatRoom, $user) === null) {
            return $this->reject($chatRoom);
        }

        return redirect()
            ->route('chat.show', $chatRoom)
            ->with('status', __('ui.chat.payment.flash.request_sent'));
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

        return redirect()->route('chat.show', $chatRoom);
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

        return redirect()
            ->route('chat.show', $chatRoom)
            ->with('status', __('ui.chat.payment.flash.received'));
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

        return redirect()->route('chat.show', $chatRoom);
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
        return redirect()
            ->route('chat.show', $chatRoom)
            ->with('status', __('ui.chat.payment.flash.completed'));
    }

    /**
     * The step did not apply: wrong actor, wrong status, or no QRIS configured.
     */
    private function reject(ChatRoom $chatRoom): RedirectResponse
    {
        return redirect()
            ->route('chat.show', $chatRoom)
            ->with('error', __('ui.chat.payment.errors.unavailable'));
    }
}
