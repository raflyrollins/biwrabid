export type AuctionStatus = 'draft' | 'active' | 'ended' | 'paid' | 'cancelled';

export type CloseReason = 'time_expired' | 'seller_ended';

export type AuctionSummary = {
    uuid: string;
    title: string;
    status: AuctionStatus;
    starting_price: number;
    current_price: number | null;
    price: string;
    starting_price_label: string;
    current_price_label: string | null;
    ends_at: string | null;
    ended_at: string | null;
    ended_reason: CloseReason | null;
    screenshot: string | null;
    seller_name: string;
};

export type AuctionDetail = AuctionSummary & {
    description: string;
    winner_name: string | null;
    reserve_price: number | null;
    reserve_price_label: string | null;
    starts_at: string | null;
    created_at: string | null;
    screenshots: { url: string; position: number }[];
};

/**
 * The viewer's part in an auction's private credential thread.
 *
 * Mirrors `ChatParticipantRole`. The label on the storefront button depends on
 * it: a seller is not offered a "chat with the seller".
 */
export type ChatRole = 'seller' | 'winner' | 'admin';

export type Bid = {
    id: number;
    bidder_name: string;
    amount: number;
    amount_label: string;
    created_at: string | null;
};

export type BiddingWindow = {
    is_open: boolean;
    preview_count: number;
    increment_label: string;
    minimum_next_bid: number;
    minimum_next_bid_label: string;
};

export type BidPlacedPayload = {
    auction_uuid: string;
    bid: Bid;
    current_price: number | null;
    current_price_label: string;
    minimum_next_bid: number;
    minimum_next_bid_label: string;
};

export type AuctionEndedPayload = {
    auction_uuid: string;
    status: AuctionStatus;
    winner_name: string | null;
    current_price: number | null;
    current_price_label: string;
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type Paginated<T> = {
    data: T[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
};
