<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\PlaceBid;
use App\Http\Requests\Bid\StoreBidRequest;
use App\Models\Auction;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class BidController extends Controller
{
    /**
     * Place a bid on an auction.
     *
     * The form request only proves the amount is a positive integer; the
     * business rules live in the action so they can be re-checked under the
     * auction row lock.
     */
    public function store(
        StoreBidRequest $request,
        Auction $auction,
        PlaceBid $placeBid,
    ): RedirectResponse {
        $this->authorize('bid', $auction);

        /** @var User $user */
        $user = $request->user();

        $placeBid($user, $auction, (int) $request->validated('amount'));

        // No flash here on purpose: bidding is repetitive, and the new price and
        // the bid history are already on screen by the time this renders.
        return back();
    }
}
