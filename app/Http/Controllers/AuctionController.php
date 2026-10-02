<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ChatRoomType;
use App\Models\Auction;
use App\Support\AuctionConfig;
use App\Support\AuctionPresenter;
use App\Support\BidPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuctionController extends Controller
{
    /**
     * The public storefront: active auctions, optionally full-text searched.
     */
    public function index(Request $request): Response
    {
        $term = $request->string('q')->trim()->value() ?: null;

        $auctions = Auction::query()
            ->active()
            ->with(['screenshots', 'seller'])
            ->search($term)
            ->when(
                $term === null,
                fn (Builder $query): Builder => $query->orderBy('ends_at'),
            )
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Auction $auction): array => AuctionPresenter::summary($auction));

        return Inertia::render('auctions/index', [
            'auctions' => $auctions,
            'filters' => ['q' => $term],
        ]);
    }

    /**
     * The public detail page for a single auction.
     */
    public function show(Auction $auction): Response
    {
        $this->authorize('view', $auction);

        $auction->load(['screenshots', 'seller', 'winner']);

        $user = request()->user();

        $bids = $auction->bids()
            ->with('bidder')
            ->limit(AuctionConfig::bidPreviewCount())
            ->get();

        $minimumNextBid = $auction->minimumNextBid();

        // Two rooms, two roles. `chat_group_role` covers the admin-visible
        // thread; `chat_credentials_role` covers the seller <-> winner handoff,
        // which the admin is excluded from. Both are the role the viewer holds,
        // because the storefront labels each button by its counterpart rather
        // than saying "chat" and leaving the reader to guess.
        $chatGroupRole = $user === null
            ? null
            : $auction->chatRoleFor(ChatRoomType::Group, $user);

        $chatCredentialsRole = $user === null
            ? null
            : $auction->chatRoleFor(ChatRoomType::Auction, $user);

        return Inertia::render('auctions/show', [
            'auction' => AuctionPresenter::detail($auction),
            'bids' => BidPresenter::collection($bids),
            'bidding' => [
                'is_open' => $auction->isOpen(),
                'preview_count' => AuctionConfig::bidPreviewCount(),
                'increment_label' => AuctionConfig::price(AuctionConfig::minimumBidIncrement()),
                'minimum_next_bid' => $minimumNextBid,
                'minimum_next_bid_label' => AuctionConfig::price($minimumNextBid),
            ],
            'can' => [
                'update' => $user?->can('update', $auction) ?? false,
                'cancel' => $user?->can('cancel', $auction) ?? false,
                'bid' => $user?->can('bid', $auction) ?? false,
                // The credential thread only exists once there is a winner to
                // receive the account, and only the seller, winner and admins
                // may open it.
                // Not just booleans: the storefront buttons have to address
                // the right counterpart. A seller opening the group thread is
                // "chat with the winner and admin", the winner is "chat with
                // the seller". Shipping one shared boolean forced a single
                // label on all three, so a seller was offered a chat with
                // themselves.
                'chat_group' => $chatGroupRole !== null,
                'chat_group_role' => $chatGroupRole?->value,
                'chat_credentials' => $chatCredentialsRole !== null,
                'chat_credentials_role' => $chatCredentialsRole?->value,
            ],
        ]);
    }
}
