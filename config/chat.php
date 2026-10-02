<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Message body
    |--------------------------------------------------------------------------
    |
    | Longest message a member may post, in characters. Kept in config rather
    | than inline so the request rules, the presenter and the front-end
    | textarea can never disagree about the limit.
    |
    */

    'message_max_length' => (int) env('CHAT_MESSAGE_MAX_LENGTH', 2000),

    /*
    |--------------------------------------------------------------------------
    | Thread pagination
    |--------------------------------------------------------------------------
    |
    | How many messages one page of the thread holds, and how many pages of
    | history the room list is allowed to look back through.
    |
    */

    'messages_per_page' => (int) env('CHAT_MESSAGES_PER_PAGE', 30),

    'rooms_per_page' => (int) env('CHAT_ROOMS_PER_PAGE', 20),

    /*
    |--------------------------------------------------------------------------
    | Payment proofs
    |--------------------------------------------------------------------------
    |
    | Payment is coordinated inside the auction's group thread, so the transfer
    | receipts are uploads against `chat_messages.proof_path` rather than a
    | separate evidence table.
    |
    | Restricted to images on purpose: a receipt is a screenshot of a banking or
    | e-wallet app, and anything else is either unusable or a way to push a file
    | at someone who did not ask for it.
    |
    */

    'proofs' => [
        'disk' => env('CHAT_PROOF_DISK', 'public'),
        'directory' => env('CHAT_PROOF_DIRECTORY', 'chat-proofs'),
        'max_size_kb' => (int) env('CHAT_PROOF_MAX_SIZE_KB', 2048),
        'max_per_step' => (int) env('CHAT_PROOF_MAX_PER_STEP', 3),
        'mimes' => ['jpg', 'jpeg', 'png', 'webp'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Admin receiving account (QRIS)
    |--------------------------------------------------------------------------
    |
    | One static QRIS for the whole platform: the admin receives every payment
    | here first and forwards it to the seller, so the amount travels in the
    | invoice message and the code itself never changes.
    |
| `image_path` is null until a real QR code is committed. Nothing can be
    | generated locally that a payment app would accept, and shipping a
    | placeholder that looks scannable would be worse than shipping nothing -
    | so the UI treats "unconfigured" as a first-class state and hides the
    | invoice button rather than sending a buyer to a dead code.
    |
    */

    'qris' => [
        'image_path' => env('CHAT_QRIS_IMAGE_PATH'),
        'account_name' => env('CHAT_QRIS_ACCOUNT_NAME'),
        'account_number' => env('CHAT_QRIS_ACCOUNT_NUMBER'),
    ],

];
