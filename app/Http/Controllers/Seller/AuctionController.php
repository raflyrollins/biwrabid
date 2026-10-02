<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\CloseAuction;
use App\Enums\AuctionStatus;
use App\Enums\CloseReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auction\PublishAuctionRequest;
use App\Http\Requests\Auction\StoreAuctionRequest;
use App\Http\Requests\Auction\UpdateAuctionRequest;
use App\Models\Auction;
use App\Models\User;
use App\Support\AuctionConfig;
use App\Support\AuctionPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AuctionController extends Controller
{
    /**
     * The auctions the signed-in seller has listed.
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $auctions = Auction::query()
            ->where('seller_id', $user->id)
            ->with(['screenshots', 'highestBid.bidder'])
            ->latest()
            ->paginate(12)
            ->through(function (Auction $auction) use ($user): array {
                $blocked = $auction->earlyCloseBlockedReason();

                return [
                    ...AuctionPresenter::summary($auction),
                    'can_end_early' => $auction->canEndEarly($user),
                    'end_early_blocked_reason' => $blocked === null
                        ? null
                        : __("ui.{$blocked}"),
                    // The seller has to see who the auction currently goes to
                    // before confirming an early close.
                    'highest_bidder_name' => $auction->highestBid?->bidder?->name,
                ];
            });

        return Inertia::render('auctions/my', [
            'auctions' => $auctions,
            'minimumDurationHours' => AuctionConfig::minimumDurationHours(),
            'defaultDurationHours' => AuctionConfig::defaultDurationHours(),
            'maximumDurationHours' => AuctionConfig::maximumDurationHours(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Auction::class);

        return Inertia::render('auctions/create', [
            'minimumStartingPrice' => AuctionConfig::minimumStartingPrice(),
            'minimumStartingPriceLabel' => AuctionConfig::price(
                AuctionConfig::minimumStartingPrice(),
            ),
            'maximumScreenshots' => AuctionConfig::maxScreenshots(),
        ]);
    }

    public function store(StoreAuctionRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        DB::transaction(function () use ($request, $user): void {
            $auction = $user->auctions()->create(
                $request->safe()->only([
                    'title',
                    'description',
                    'starting_price',
                    'reserve_price',
                ]),
            );

            /** @var array<int, UploadedFile> $files */
            $files = $request->file('screenshots', []);

            foreach (array_values($files) as $position => $file) {
                $auction->screenshots()->create([
                    'path' => $file->store(
                        AuctionConfig::screenshotsDirectory(),
                        AuctionConfig::screenshotsDisk(),
                    ),
                    'position' => $position,
                ]);
            }
        });

        return redirect()
            ->route('my-auctions.index')
            ->with('status', __('ui.auctions.created'));
    }

    public function edit(Auction $auction): Response
    {
        $this->authorize('update', $auction);

        $auction->load('screenshots');

        return Inertia::render('auctions/edit', [
            'auction' => AuctionPresenter::detail($auction),
            'minimumStartingPrice' => AuctionConfig::minimumStartingPrice(),
            'minimumStartingPriceLabel' => AuctionConfig::price(
                AuctionConfig::minimumStartingPrice(),
            ),
        ]);
    }

    public function update(UpdateAuctionRequest $request, Auction $auction): RedirectResponse
    {
        $this->authorize('update', $auction);

        $auction->update($request->validated());

        return redirect()
            ->route('my-auctions.index')
            ->with('status', __('ui.auctions.updated'));
    }

    public function destroy(Auction $auction): RedirectResponse
    {
        $this->authorize('delete', $auction);

        $auction->delete();

        return redirect()
            ->route('my-auctions.index')
            ->with('status', __('ui.auctions.deleted'));
    }

    public function publish(PublishAuctionRequest $request, Auction $auction): RedirectResponse
    {
        $this->authorize('publish', $auction);

        $endsAt = Carbon::parse($request->validated('ends_at'));

        $auction->forceFill([
            'status' => AuctionStatus::Active,
            'starts_at' => now(),
            'ends_at' => $endsAt,
        ])->save();

        return redirect()
            ->route('auctions.show', $auction)
            ->with('status', __('ui.auctions.published'));
    }

    public function cancel(Auction $auction): RedirectResponse
    {
        $this->authorize('cancel', $auction);

        $auction->forceFill(['status' => AuctionStatus::Cancelled])->save();

        return redirect()
            ->route('my-auctions.index')
            ->with('status', __('ui.auctions.cancelled'));
    }

    /**
     * Stop bidding early and hand the auction to the highest bidder on the spot.
     *
     * Distinct from cancel(): this awards the auction, while cancel() pulls it
     * from the market with no winner at all.
     */
    public function endEarly(Auction $auction, CloseAuction $closeAuction): RedirectResponse
    {
        $this->authorize('endEarly', $auction);

        $blocked = $auction->earlyCloseBlockedReason();

        if ($blocked !== null) {
            return redirect()
                ->route('my-auctions.index')
                ->with('error', __("ui.{$blocked}"));
        }

        $closed = $closeAuction($auction, CloseReason::SellerEnded);

        if ($closed === null) {
            // The auction stopped being active between the authorize() call and
            // the row lock - a scheduler run or a cancel won the race.
            return redirect()
                ->route('my-auctions.index')
                ->with('error', __('ui.auctions.early_close.errors.not_active'));
        }

        return redirect()
            ->route('my-auctions.index')
            ->with('status', __('ui.auctions.early_close.ended', [
                'name' => $closed->winner?->name,
                'price' => AuctionConfig::price($closed->effectivePrice()),
            ]));
    }
}
