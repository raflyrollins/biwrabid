/**
 * `support` is member <-> admin, `group` is seller <-> winner <-> admin, and
 * `auction` is the private seller <-> winner credential handoff that excludes
 * the admin. Mirrors `ChatRoomType`.
 */
export type ChatRoomKind = 'support' | 'group' | 'auction';

export type ChatParticipantRole = 'member' | 'seller' | 'winner' | 'admin';

/**
 * Mirrors `ChatMessageKind`. Everything other than `message` is the payment
 * trail, which is why those live in the transcript: a dispute is settled by
 * reading the thread.
 */
export type ChatMessageKind =
    | 'message'
    | 'payment_request'
    | 'payment_proof'
    | 'payment_received'
    | 'transfer_proof'
    | 'payment_completed';

export type ChatMessageAttachment = {
    name: string;
    size: number;
    url: string;
};

export type ChatMessage = {
    id: number;
    body: string;
    kind: ChatMessageKind;
    /** Set on the invoice only: the snapshot the winner was quoted. */
    amount: number | null;
    /** Non-null only for the two proof kinds. */
    proof_url: string | null;
    /** Non-null only on the invoice: the QRIS the admin uploaded. */
    qris_url: string | null;
    attachments: ChatMessageAttachment[];
    /**
     * The author's user id. This — not `sender_role` — is what "is this my own
     * message" is matched against: two people in one room can hold the same role,
     * and in a support thread the admin and the member are the only two parties.
     */
    sender_id: number | null;
    sender_name: string | null;
    sender_role: ChatParticipantRole;
    created_at: string | null;
    /**
     * Whether every other participant in the room has read this message. Decided
     * on the server because it depends on who is in the room.
     */
    read_by_all: boolean;
};

export type ChatCounterparty = {
    name: string;
    role: ChatParticipantRole;
};

/** Mirrors `PaymentStatus`. */
export type PaymentStatus =
    | 'requested'
    | 'received'
    | 'transferred'
    | 'completed';

/**
 * The five steps of the payment flow, named after the routes that perform them.
 * `actions` is what this viewer may do *right now*, decided server-side so a
 * button cannot disagree with its endpoint.
 */
export type PaymentAction =
    | 'request'
    | 'proof'
    | 'received'
    | 'transfer'
    | 'confirm';

export type PaymentPanel = {
    status: PaymentStatus | null;
    /** The invoiced total, or the quote an invoice would be raised at. */
    amount: number | null;
    bid_amount: number | null;
    admin_fee: number;
    /** The QRIS the admin put on the invoice; null before one is sent. */
    qris: {
        image_url: string;
        account_name: string | null;
        account_number: string | null;
    } | null;
    /** Whether either receipt has been uploaded into this thread yet. */
    has_proof: boolean;
    actions: PaymentAction[];
};

export type ChatRoom = {
    uuid: string;
    kind: ChatRoomKind;
    role: ChatParticipantRole | null;
    /** False for the credential thread, which keeps the admin out. */
    admits_admin: boolean;
    updated_at: string | null;
    counterparties: ChatCounterparty[];
    auction: {
        uuid: string;
        title: string;
        status: string;
        image_url: string | null;
    } | null;
    latest_message: ChatMessage | null;
};

/**
 * The payment trail, paired with the group room the payment routes bind to.
 *
 * Lives on the auction page: the steps are auction state, and the thread keeps
 * only the transcript.
 */
export type AuctionPayment = {
    room_uuid: string;
    panel: PaymentPanel;
};

export type ChatThread = {
    messages: ChatMessage[];
    total: number;
    current_page: number;
    last_page: number;
    older_url: string | null;
    newer_url: string | null;
};

export type MessageSentPayload = {
    room_uuid: string;
    message: ChatMessage;
};
