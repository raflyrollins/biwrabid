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
    | Payment uploads
    |--------------------------------------------------------------------------
    |
    | Payment is coordinated inside the auction's group thread, so the QRIS and
    | the two transfer receipts are all uploads against `chat_messages` rather
    | than a separate evidence table. The QRIS is uploaded by the admin when
    | they raise the invoice and stored on that message (`qris_path`), which
    | keeps the code the winner paid to attached to the invoice even after the
    | platform's receiving account changes.
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
    | Chat attachments
    |--------------------------------------------------------------------------
    |
    | Files anyone in a room may attach to an ordinary message: the screenshot of
    | an account's stats, a photo of the goods, a PDF of the specs. This is the
    | whole reason the thread exists, so it is not gated behind a payment step.
    |
    | Images and PDF only. An executable or an archive is either unusable in the
    | thread or a way to push a file at someone who never asked for one, and
    | those are exactly the attachments a marketplace chat does not need.
    |
    */

    'attachments' => [
        'disk' => env('CHAT_ATTACHMENT_DISK', 'public'),
        'directory' => env('CHAT_ATTACHMENT_DIRECTORY', 'chat-attachments'),
        'max_size_kb' => (int) env('CHAT_ATTACHMENT_MAX_SIZE_KB', 4096),
        'max_per_message' => (int) env('CHAT_ATTACHMENT_MAX_PER_MESSAGE', 6),
        'mimes' => ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Admin receiving account labels
    |--------------------------------------------------------------------------
    |
    | Optional text shown beside the QRIS image the admin uploaded, so a winner
    | can check they are paying the right account holder. Neither key gates the
    | invoice action: the QRIS image is the part a payment app reads.
    |
    */

    'qris' => [
        'account_name' => env('CHAT_QRIS_ACCOUNT_NAME'),
        'account_number' => env('CHAT_QRIS_ACCOUNT_NUMBER'),
    ],

];
