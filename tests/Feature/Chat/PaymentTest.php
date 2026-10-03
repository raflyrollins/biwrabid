<?php

declare(strict_types=1);

use App\Actions\CoordinatePayment;
use App\Enums\AuctionStatus;
use App\Enums\ChatMessageKind;
use App\Enums\ChatRoomType;
use App\Enums\PaymentStatus;
use App\Models\Auction;
use App\Models\AuctionPayment;
use App\Models\Bid;
use App\Models\ChatRoom;
use App\Models\User;
use App\Support\ChatPresenter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Payment happens inside the auction's *group* thread, and the admin holds the
 * money in between. These tests walk the trail, then try to break it from every
 * side: the wrong actor, the wrong step, the wrong room.
 */

/**
 * An ended auction with a winner and a group room.
 *
 * The receiving account *labels* are optional settings; the QRIS image is not,
 * because the admin uploads the one they are actually paid on. Only the two text
 * keys are stubbed here.
 *
 * @return array{0: Auction, 1: ChatRoom, 2: User, 3: User, 4: User, 5: User}
 *                                                                            auction, group room, seller, winner, admin, stranger
 */
function paymentScenario(int $fee = 0): array
{
    config()->set('auction.admin_fee_flat', $fee);
    config()->set('chat.qris.account_name', 'PT Contoh');
    config()->set('chat.qris.account_number', '1234567890');

    $seller = User::factory()->create();
    $winner = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $stranger = User::factory()->create();

    $auction = Auction::factory()->for($seller, 'seller')->create([
        'status' => AuctionStatus::Ended,
        'winner_id' => $winner->id,
        'starting_price' => 1_000_000,
    ]);

    Bid::factory()->for($auction, 'auction')->for($winner, 'bidder')->create([
        'amount' => 1_500_000,
    ]);

    $auction->refresh();

    return [
        $auction,
        ChatRoom::factory()->group($auction)->create(),
        $seller,
        $winner,
        $admin,
        $stranger,
    ];
}

function receipt(): UploadedFile
{
    return UploadedFile::fake()->image('bukti.jpg', 400, 800);
}

/**
 * The receiving account the admin uploads when they raise the invoice.
 *
 * A file rather than a configured path, because the code the winner pays is
 * snapshotted onto the invoice: a QRIS swapped in `config` afterwards would
 * silently re-point a live invoice at a different account.
 */
function qris(): UploadedFile
{
    return UploadedFile::fake()->image('qris.png', 300, 300);
}

/**
 * Run the trail up to (but not including) the seller's confirmation.
 */
function trailUpToTransfer(ChatRoom $room, User $winner, User $admin): CoordinatePayment
{
    $payment = app(CoordinatePayment::class);

    $payment->sendRequest($room, $admin, qris());
    $payment->recordPaymentProof($room, $winner, receipt());
    $payment->markReceived($room, $admin);
    $payment->recordTransferProof($room, $admin, receipt());

    return $payment;
}

beforeEach(function (): void {
    Storage::fake('public');
});

it('invoices the winning bid plus the admin fee and snapshots it', function (): void {
    [$auction, $room, , , $admin] = paymentScenario(fee: 25_000);

    $message = app(CoordinatePayment::class)->sendRequest($room, $admin, qris());

    // 1.500.000 won + 25.000 fee. The snapshot is what protects an outstanding
    // invoice from being re-priced when the fee changes later.
    expect($message?->kind)->toBe(ChatMessageKind::PaymentRequest)
        ->and($message?->amount)->toBe(1_525_000)
        ->and($message?->proof_path)->toBeNull();

    expect($auction->payment()->first()?->amount)->toBe(1_525_000)
        ->and($auction->payment()->first()?->status)->toBe(PaymentStatus::Requested);
});

it('prices the invoice from the highest bid rather than the cached price', function (): void {
    [$auction, $room, , , $admin] = paymentScenario();

    // `current_price` is a cache written by PlaceBid; the invoice must not read
    // it, or a drifted row would be billed.
    $auction->forceFill(['current_price' => 999_999])->save();

    app(CoordinatePayment::class)->sendRequest($room, $admin, qris());

    expect($auction->payment()->first()?->amount)->toBe(1_500_000);
});

it('refuses to raise an invoice without a QRIS to pay', function (): void {
    [$auction, $room, , , $admin] = paymentScenario();

    // The code the winner pays is uploaded with the invoice and snapshotted onto
    // it. Without one there is nothing to send them to, so the endpoint rejects
    // the request rather than invoicing an amount nobody can settle.
    $this->actingAs($admin)
        ->post(route('chat.payment.request', $room))
        ->assertSessionHasErrors('qris');

    expect($auction->payment()->exists())->toBeFalse()
        ->and(
            $room->messages()
                ->where('kind', ChatMessageKind::PaymentRequest->value)
                ->count(),
        )->toBe(0);
});

it('refuses to invoice a live auction with no winner', function (): void {
    $admin = User::factory()->admin()->create();
    $auction = Auction::factory()->active()->create();
    $room = ChatRoom::factory()->group($auction)->create();

    expect(app(CoordinatePayment::class)->sendRequest($room, $admin, qris()))->toBeNull()
        ->and($auction->payment()->exists())->toBeFalse();
});

it('will not invoice twice, nor re-price one already in flight', function (): void {
    [$auction, $room, , , $admin] = paymentScenario(fee: 25_000);
    $payment = app(CoordinatePayment::class);

    expect($payment->sendRequest($room, $admin, qris()))->not->toBeNull();

    // The fee changes *after* the winner has been invoiced. Re-sending must not
    // re-price the outstanding invoice.
    config()->set('auction.admin_fee_flat', 99_000);

    expect($payment->sendRequest($room, $admin, qris()))->toBeNull()
        ->and($auction->payment()->first()?->amount)->toBe(1_525_000)
        ->and($auction->payment()->count())->toBe(1)
        ->and(
            $room->messages()
                ->where('kind', ChatMessageKind::PaymentRequest->value)
                ->count(),
        )->toBe(1);
});

it('will not let a stale send drag an advanced payment back to requested', function (): void {
    [$auction, $room, , $winner, $admin] = paymentScenario();

    // Walk as far as `received`, then replay the request the admin already took.
    $payment = app(CoordinatePayment::class);
    $payment->sendRequest($room, $admin, qris());
    $payment->recordPaymentProof($room, $winner, receipt());
    $payment->markReceived($room, $admin);

    expect($payment->sendRequest($room, $admin, qris()))->toBeNull()
        ->and($auction->payment()->first()?->status)->toBe(PaymentStatus::Received);
});

it('will not confirm funds received before the winner has sent a receipt', function (): void {
    [$auction, $room, , , $admin] = paymentScenario();
    $payment = app(CoordinatePayment::class);

    $payment->sendRequest($room, $admin, qris());

    // The admin is vouching that the account matches something the winner sent.
    expect($payment->markReceived($room, $admin))->toBeNull()
        ->and($auction->payment()->first()?->status)->toBe(PaymentStatus::Requested);

    // Nor is the button offered in the first place.
    expect(ChatPresenter::payment($room, $admin)['actions'])->toBe([]);

    $payment->recordPaymentProof($room, $room->auction->winner, receipt());

    expect(ChatPresenter::payment($room, $admin)['actions'])->toBe(['received'])
        ->and($payment->markReceived($room, $admin))->not->toBeNull();
});

it('bounds how many receipts one step may attach', function (): void {
    config()->set('chat.proofs.max_per_step', 2);

    [$auction, $room, , $winner, $admin] = paymentScenario();
    $payment = app(CoordinatePayment::class);

    $payment->sendRequest($room, $admin, qris());

    expect($payment->recordPaymentProof($room, $winner, receipt()))->not->toBeNull()
        ->and($payment->recordPaymentProof($room, $winner, receipt()))->not->toBeNull()
        ->and($payment->recordPaymentProof($room, $winner, receipt()))->toBeNull()
        ->and(
            $room->messages()
                ->where('kind', ChatMessageKind::PaymentProof->value)
                ->count(),
        )->toBe(2)
        // The refused upload must not be left on the disk either. Three files in
        // all: the QRIS the invoice carries, plus the two receipts that stuck.
        ->and(Storage::disk('public')->allFiles('chat-proofs'))->toHaveCount(3);
});

it('walks the whole trail and only the seller can close it', function (): void {
    [, $room, $seller, $winner, $admin] = paymentScenario();
    $payment = trailUpToTransfer($room, $winner, $admin);

    // The winner is not the party the admin debited, so they cannot vouch for
    // the seller having been paid.
    expect($payment->confirmPayment($room, $winner))->toBeNull();

    $confirmed = $payment->confirmPayment($room, $seller);

    expect($confirmed?->status)->toBe(AuctionStatus::Paid)
        ->and($confirmed?->payment()->first()?->status)->toBe(PaymentStatus::Completed);
});

it('closes out exactly once however often the seller confirms', function (): void {
    [, $room, $seller, $winner, $admin] = paymentScenario();
    $payment = trailUpToTransfer($room, $winner, $admin);

    expect($payment->confirmPayment($room, $seller))->not->toBeNull()
        ->and($payment->confirmPayment($room, $seller))->toBeNull()
        ->and($payment->confirmPayment($room, $seller))->toBeNull();

    expect(
        $room->messages()
            ->where('kind', ChatMessageKind::PaymentCompleted->value)
            ->count(),
    )->toBe(1);
});

it('stores each receipt on the public disk', function (): void {
    [, $room, , $winner, $admin] = paymentScenario();
    $payment = app(CoordinatePayment::class);

    $payment->sendRequest($room, $admin, qris());
    $payment->recordPaymentProof($room, $winner, receipt());
    $payment->markReceived($room, $admin);
    $message = $payment->recordTransferProof($room, $admin, receipt());

    expect($message?->proof_path)->not->toBeNull()
        ->and(Storage::disk('public')->exists($message?->proof_path ?? ''))->toBeTrue()
        ->and($message?->proofUrl())->toContain($message?->proof_path ?? '');
});

it('keeps a proof path off an ordinary message', function (): void {
    [, $room, $seller] = paymentScenario();

    $chat = $room->messages()->create([
        'user_id' => $seller->id,
        'body' => 'halo',
    ]);

    // The URL is gated on the kind, so ordinary chatter cannot surface a file
    // path even if one were set on the row by accident.
    expect($chat->kind)->toBe(ChatMessageKind::Message)
        ->and($chat->proofUrl())->toBeNull();
});

it('removes the stored file when the step turns out not to apply', function (): void {
    [, $room, , $winner, $admin] = paymentScenario();
    $payment = trailUpToTransfer($room, $winner, $admin);

    // Already transferred, so this upload must be refused — and must not leave
    // an orphaned file behind. Three files in all: the invoice's QRIS and the two
    // receipts the trail actually posted.
    expect($payment->recordPaymentProof($room, $winner, receipt()))->toBeNull()
        ->and(Storage::disk('public')->allFiles('chat-proofs'))->toHaveCount(3);
});

it('keeps each step to its own actor', function (): void {
    [, $room, $seller, $winner, $admin, $stranger] = paymentScenario();
    $payment = app(CoordinatePayment::class);

    // Only the admin invoices.
    expect($payment->sendRequest($room, $winner, qris()))->toBeNull()
        ->and($payment->sendRequest($room, $seller, qris()))->toBeNull()
        ->and($payment->sendRequest($room, $stranger, qris()))->toBeNull()
        ->and($payment->sendRequest($room, $admin, qris()))->not->toBeNull();

    // Only the winner attaches the incoming receipt.
    expect($payment->recordPaymentProof($room, $seller, receipt()))->toBeNull()
        ->and($payment->recordPaymentProof($room, $admin, receipt()))->toBeNull()
        ->and($payment->recordPaymentProof($room, $winner, receipt()))->not->toBeNull();

    // Only the admin confirms their own account, and only they pay the seller.
    expect($payment->markReceived($room, $winner))->toBeNull()
        ->and($payment->markReceived($room, $seller))->toBeNull()
        ->and($payment->markReceived($room, $admin))->not->toBeNull();

    expect($payment->recordTransferProof($room, $winner, receipt()))->toBeNull()
        ->and($payment->recordTransferProof($room, $seller, receipt()))->toBeNull()
        ->and($payment->recordTransferProof($room, $admin, receipt()))->not->toBeNull();

    // Only the seller closes it out.
    expect($payment->confirmPayment($room, $winner))->toBeNull()
        ->and($payment->confirmPayment($room, $admin))->toBeNull()
        ->and($payment->confirmPayment($room, $seller))->not->toBeNull();
});

it('never runs the money flow in the credential room', function (): void {
    [$auction, $room, $seller, $winner] = paymentScenario();

    $credentials = ChatRoom::factory()->auction($auction)->create();
    $payment = app(CoordinatePayment::class);

    // The credential room has the same two non-admin parties as the group room,
    // so a check that only asked "seller or winner" would happily move real
    // money inside the one thread the admin cannot see.
    expect($payment->sendRequest($credentials, $seller, qris()))->toBeNull()
        ->and($payment->recordPaymentProof($credentials, $winner, receipt()))->toBeNull()
        ->and($payment->markReceived($credentials, $seller))->toBeNull()
        ->and($payment->recordTransferProof($credentials, $seller, receipt()))->toBeNull()
        ->and($payment->confirmPayment($credentials, $seller))->toBeNull();

    expect($auction->payment()->exists())->toBeFalse()
        ->and(
            $credentials->messages()
                ->whereNot('kind', ChatMessageKind::Message->value)
                ->count(),
        )->toBe(0);
});

it('exposes the payment panel only on an auction group thread', function (): void {
    [, $room, , , $admin] = paymentScenario();

    $member = User::factory()->create();
    $support = ChatRoom::factory()->support($member)->create();
    $credentials = ChatRoom::factory()->auction($room->auction)->create();

    expect(ChatPresenter::payment($room, $admin))->not->toBeNull()
        ->and(ChatPresenter::payment($support, $admin))->toBeNull()
        ->and(ChatPresenter::payment($credentials, $admin))->toBeNull();
});

it('offers each role only the step it is responsible for', function (): void {
    [, $room, $seller, $winner, $admin] = paymentScenario();
    $payment = app(CoordinatePayment::class);

    // Before an invoice exists, only the admin is offered anything.
    expect(ChatPresenter::payment($room, $admin)['actions'])->toBe(['request'])
        ->and(ChatPresenter::payment($room, $winner)['actions'])->toBe([])
        ->and(ChatPresenter::payment($room, $seller)['actions'])->toBe([]);

    $payment->sendRequest($room, $admin, qris());

    // Then the winner. The admin gets nothing until the receipt is in, because
    // confirming their own account has to be checked against something.
    expect(ChatPresenter::payment($room, $winner)['actions'])->toBe(['proof'])
        ->and(ChatPresenter::payment($room, $admin)['actions'])->toBe([])
        ->and(ChatPresenter::payment($room, $seller)['actions'])->toBe([]);

    $payment->recordPaymentProof($room, $winner, receipt());

    expect(ChatPresenter::payment($room, $admin)['actions'])->toBe(['received']);

    $payment->markReceived($room, $admin);

    expect(ChatPresenter::payment($room, $admin)['actions'])->toBe(['transfer'])
        ->and(ChatPresenter::payment($room, $winner)['actions'])->toBe([]);

    $payment->recordTransferProof($room, $admin, receipt());

    expect(ChatPresenter::payment($room, $seller)['actions'])->toBe(['confirm'])
        ->and(ChatPresenter::payment($room, $admin)['actions'])->toBe([])
        ->and(ChatPresenter::payment($room, $winner)['actions'])->toBe([]);

    $payment->confirmPayment($room, $seller);

    expect(ChatPresenter::payment($room, $seller)['actions'])->toBe([])
        ->and(ChatPresenter::payment($room, $admin)['actions'])->toBe([]);
});

it('keeps the panel closed to a bystander', function (): void {
    [, $room, , , $admin, $stranger] = paymentScenario();

    app(CoordinatePayment::class)->sendRequest($room, $admin, qris());

    expect($room->canAccess($stranger))->toBeFalse();
});

it('reports the payment state on the auction page, and the transcript in the thread', function (): void {
    [$auction, $room, , $winner, $admin] = paymentScenario();

    $invoice = app(CoordinatePayment::class)->sendRequest($room, $admin, qris());

    // The steps are auction state, so the panel that renders them lives on the
    // auction page and only the transcript stays in the thread.
    $this->actingAs($winner)
        ->get(route('auctions.show', $auction))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('payment.room_uuid', $room->uuid)
            ->where('payment.panel.status', 'requested')
            ->where('payment.panel.amount', 1_500_000)
            ->where('payment.panel.bid_amount', 1_500_000)
            ->where('payment.panel.has_proof', false)
            ->where('payment.panel.actions', ['proof'])
            ->where('payment.panel.qris.image_url', $invoice?->qrisUrl())
        );

    $this->actingAs($winner)
        ->get(route('chat.index', ['c' => $room->uuid]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('chat/index')
            ->has('thread.messages', 1)
            ->where('thread.messages.0.kind', 'payment_request')
            ->where('thread.messages.0.amount', 1_500_000)
            ->where('thread.messages.0.proof_url', null)
            // The QRIS is snapshotted onto the invoice, so the winner scrolling
            // back up finds the code they were actually asked to pay.
            ->where('thread.messages.0.qris_url', $invoice?->qrisUrl())
        );
});

it('exposes the receipt image once it has been uploaded', function (): void {
    [$auction, $room, , $winner, $admin] = paymentScenario();

    app(CoordinatePayment::class)->sendRequest($room, $admin, qris());
    $message = app(CoordinatePayment::class)->recordPaymentProof($room, $winner, receipt());

    $this->actingAs($winner)
        ->get(route('auctions.show', $auction))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('payment.panel.has_proof', true)
        );

    $this->actingAs($winner)
        ->get(route('chat.index', ['c' => $room->uuid]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('thread.messages', 2)
            ->where('thread.messages.1.kind', 'payment_proof')
            ->where('thread.messages.1.proof_url', $message?->proofUrl())
        );
});

it('keeps the receiving account readable after invoicing', function (): void {
    [$auction, $room, , $winner, $admin] = paymentScenario();

    app(CoordinatePayment::class)->sendRequest($room, $admin, qris());

    // The labels are optional settings shown beside the uploaded code, and they
    // have to be readable for as long as the invoice is: the QRIS itself is
    // snapshotted onto the message, so nothing here can be quietly re-pointed.
    $this->actingAs($winner)
        ->get(route('auctions.show', $auction))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('payment.panel.qris.account_name', 'PT Contoh')
            ->where('payment.panel.qris.account_number', '1234567890')
            ->where('payment.panel.status', 'requested')
        );
});

it('offers no payment panel before a group thread exists', function (): void {
    $seller = User::factory()->create();
    $winner = User::factory()->create();

    $auction = Auction::factory()->for($seller, 'seller')->create([
        'status' => AuctionStatus::Ended,
        'winner_id' => $winner->id,
    ]);

    // No group room: the panel has no thread to coordinate in, and offering the
    // steps anyway would produce buttons whose endpoints do not exist.
    $this->actingAs($winner)
        ->get(route('auctions.show', $auction))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('payment', null));
});

it('drives the trail through the routes and flashes the outcome', function (): void {
    [$auction, $room, $seller, $winner, $admin] = paymentScenario();

    // Every step lands back on the auction, because the panel the actor just used
    // is there — not on the thread, which would throw them out of the screen they
    // acted on.
    $this->actingAs($admin)
        ->post(route('chat.payment.request', $room), ['qris' => qris()])
        ->assertRedirect(route('auctions.show', $auction))
        ->assertSessionHas('status');

    $this->actingAs($winner)
        ->post(route('chat.payment.proof', $room), ['proof' => receipt()])
        ->assertRedirect(route('auctions.show', $auction));

    $this->actingAs($admin)
        ->post(route('chat.payment.received', $room))
        ->assertRedirect(route('auctions.show', $auction))
        ->assertSessionHas('status');

    $this->actingAs($admin)
        ->post(route('chat.payment.transfer', $room), ['proof' => receipt()])
        ->assertRedirect(route('auctions.show', $auction));

    $this->actingAs($seller)
        ->post(route('chat.payment.confirm', $room))
        ->assertRedirect(route('auctions.show', $auction))
        ->assertSessionHas('status');

    expect($auction->fresh()?->status)->toBe(AuctionStatus::Paid);
});

it('refuses a receipt upload that is not an image', function (): void {
    [, $room, , $winner, $admin] = paymentScenario();

    app(CoordinatePayment::class)->sendRequest($room, $admin, qris());

    $this->actingAs($winner)
        ->post(route('chat.payment.proof', $room), [
            'proof' => UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf'),
        ])
        ->assertSessionHasErrors('proof');

    expect(
        $room->messages()
            ->where('kind', ChatMessageKind::PaymentProof->value)
            ->count(),
    )->toBe(0);
});

it('requires a receipt to be attached', function (): void {
    [, $room, , $winner, $admin] = paymentScenario();

    app(CoordinatePayment::class)->sendRequest($room, $admin, qris());

    $this->actingAs($winner)
        ->post(route('chat.payment.proof', $room))
        ->assertSessionHasErrors('proof');
});

it('rejects an outsider before the payment endpoint is reached', function (): void {
    [, $room, , , , $stranger] = paymentScenario();

    $this->actingAs($stranger)
        ->post(route('chat.payment.request', $room))
        ->assertForbidden();

    $this->actingAs($stranger)
        ->post(route('chat.payment.proof', $room), ['proof' => receipt()])
        ->assertForbidden();

    $this->actingAs($stranger)
        ->post(route('chat.payment.confirm', $room))
        ->assertForbidden();

    expect(AuctionPayment::query()->count())->toBe(0);
});

it('refuses an early upload before the invoice exists', function (): void {
    [$auction, $room, , $winner] = paymentScenario();

    // No invoice yet: there is nothing for a receipt to be evidence *of*.
    $this->actingAs($winner)
        ->post(route('chat.payment.proof', $room), ['proof' => receipt()])
        ->assertRedirect(route('auctions.show', $auction))
        ->assertSessionHas('error');

    expect(AuctionPayment::query()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles('chat-proofs'))->toBe([]);
});

it('keeps the room kind in the inbox badge', function (): void {
    [, $room, , , $admin] = paymentScenario();

    app(CoordinatePayment::class)->sendRequest($room, $admin, qris());

    $this->actingAs($admin)
        ->get(route('chat.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('rooms.data.0.kind', ChatRoomType::Group->value)
        );
});
