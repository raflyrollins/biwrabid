<?php

return [

    'welcome' => [
        'title' => 'Home',
        'headline' => 'Auction Mobile Legends accounts with confidence.',
        'subheadline' => 'Sell and win your favorite ML accounts. Every bid updates in real-time.',
        'login' => 'Log in',
        'register' => 'Sign up',
        'greeting' => 'Hi, :name',
        'logout' => 'Log out',
        'cta_browse' => 'Browse auctions',

        'hero' => [
            'eyebrow' => 'The ML account market for Indonesian players',
            'live' => 'Live auction',
            'current_bid' => 'Highest bid',
            'account' => 'Mythic account · 500 skins',
            'bid_value' => 'IDR 12,500,000',
            'bidders' => ':count bidders',
            'popular' => 'Popular heroes',
            'stat_accounts_label' => 'Accounts sold',
            'stat_accounts_value' => '3,200+',
            'stat_heroes_label' => 'Heroes listed',
            'stat_heroes_value' => '120+',
            'stat_safe_label' => 'Secure deals',
            'stat_safe_value' => '100%',
        ],

        'heroes' => [
            'title' => 'From Layla to Ling — you name it.',
        ],

        'steps' => [
            'title' => 'How it works',
            'subtitle' => 'Three simple steps from browsing to owning the account.',
            'browse_title' => 'Find an account',
            'browse_body' => 'Browse live auctions and find the account with the rank, skins, and heroes you want.',
            'win_title' => 'Win the auction',
            'win_body' => 'Place the highest bid. Every bid shows up instantly, no refresh needed.',
            'pay_title' => 'Pay safely',
            'pay_body' => 'Once you win, settle the payment and receive the account details from the seller.',
        ],

        'features' => [
            'title' => 'Buy and sell without worry',
            'subtitle' => 'We keep every step safe so you can focus on winning.',
            'secure_title' => 'Account data protected',
            'secure_body' => 'Account credentials are encrypted and revealed only to the winning buyer.',
            'realtime_title' => 'Real-time bidding',
            'realtime_body' => 'Every new bid appears instantly, so you always know where you stand.',
            'chat_title' => 'Chat with the seller',
            'chat_body' => 'Ask about the account details before you start bidding.',
        ],

        'cta' => [
            'title' => 'Ready to win your dream account?',
            'body' => 'Create a free account, join an auction, and take home your best ML account today.',
        ],

        'footer' => [
            'tagline' => 'A safe, transparent, real-time marketplace for Mobile Legends accounts.',
            'explore' => 'Explore',
            'account' => 'Account',
            'sell' => 'Sell account',
            'disclaimer' => 'Mobile Legends: Bang Bang is a trademark of Moonton. This site is not affiliated with Moonton.',
        ],
    ],

    'flash' => [
        'success' => 'Done',
        'error' => 'Something went wrong',
        'dismiss' => 'Got it',
    ],

    'nav' => [
        'home' => 'Home',
        'auctions' => 'Auctions',
        'my_auctions' => 'My Auctions',
        'sell' => 'Sell Account',
        'chat' => 'Chat',
        'support' => 'Support',
    ],

    'auth' => [
        'login' => [
            'title' => 'Log in',
            'subtitle' => 'Log in to start bidding or selling an account.',
            'email' => 'Email',
            'password' => 'Password',
            'remember' => 'Remember me',
            'submit' => 'Log in',
            'prompt' => "Don't have an account?",
            'action' => 'Sign up',
        ],
        'register' => [
            'title' => 'Sign up',
            'subtitle' => 'Create an account to start selling or bidding.',
            'name' => 'Name',
            'email' => 'Email',
            'password' => 'Password',
            'password_confirmation' => 'Confirm password',
            'submit' => 'Sign up',
            'prompt' => 'Already have an account?',
            'action' => 'Log in',
        ],

        'panel' => [
            'title' => 'Auction ML accounts without the drama.',
            'body' => 'Thousands of players trade accounts safely. Join and start bidding in minutes.',
            'point_secure' => 'Accounts and credentials stay protected',
            'point_realtime' => 'Real-time bids with no refresh',
            'point_chat' => 'Chat directly with the seller',
        ],
    ],

    'auctions' => [
        'created' => 'Auction created.',
        'updated' => 'Auction updated.',
        'deleted' => 'Auction deleted.',
        'published' => 'Auction published.',
        'cancelled' => 'Auction cancelled.',

        'confirm' => [
            'cancel_button' => 'Cancel',

            'publish' => [
                'title' => 'Publish this auction?',
                'body' => 'Once published, buyers can bid immediately and you can no longer change the title or the price.',
                'confirm' => 'Yes, publish',
            ],

            'delete' => [
                'title' => 'Delete this draft?',
                'body' => 'The draft and its screenshots are permanently deleted.',
                'confirm' => 'Delete',
            ],

            'cancel' => [
                'title' => 'Cancel this auction?',
                'body' => 'The auction is pulled from the listings and nobody can bid on it again. Bids already placed are not paid out.',
                'confirm' => 'Yes, cancel',
            ],

            'end_early' => [
                'title' => 'Close this auction now?',
                'confirm' => 'Yes, close now',
            ],
        ],

        'early_close' => [
            'label' => 'Close now',
            'hint_no_bids' => 'There are no bids, so the auction will close with no winner.',
            'hint_with_bids' => 'The auction goes straight to :name at :price.',
            'ended' => 'Auction closed early. :name won at :price.',

            'errors' => [
                'not_active' => 'This auction is no longer active.',
                'below_reserve' => 'The highest bid is still below your reserve price.',
                'too_recent_bid' => 'A bid just came in. Wait a few minutes before closing the auction.',
            ],
        ],

        'bids' => [
            'title' => 'Bid History',
            'empty' => 'No bids yet. Be the first!',
            'amount' => 'Bid (IDR)',
            'place' => 'Place bid',
            'minimum' => 'Minimum :amount',
            'increment' => 'Minimum increment :amount',
            'use_minimum' => 'Use minimum',
            'highest' => 'Highest',
            'live' => 'Live',
            'winner' => 'Winner: :name',
            'closed' => 'Bidding is closed.',
            'login' => 'Log in to bid',
            'cannot_bid' => "You can't bid on your own auction.",
            'errors' => [
                'closed' => 'This auction is not open for bidding.',
                'own' => "You can't bid on your own auction.",
                'too_low' => 'The minimum bid is :amount.',
            ],
        ],

        'status' => [
            'draft' => 'Draft',
            'active' => 'Active',
            'ended' => 'Ended',
            'paid' => 'Paid',
            'cancelled' => 'Cancelled',
        ],

        'index' => [
            'title' => 'Auctions',
            'headline' => 'Mobile Legends Account Auctions',
            'subtitle' => 'Find the ML account you want and win the bidding.',
            'search_placeholder' => 'Search accounts, heroes, or keywords...',
            'search' => 'Search',
            'empty' => 'No active auctions right now.',
            'empty_title' => 'No auctions yet',
            'empty_body' => 'Be the first seller and start auctioning your ML account.',
            'empty_search_title' => 'Auction not found',
            'empty_search_body' => 'No auctions match your search. Try another keyword.',
            'clear_search' => 'View all auctions',
            'starting_price' => 'Starting price',
            'current_price' => 'Highest bid',
            'ends_at' => 'Ends',
            'view' => 'View auction',
        ],

        'show' => [
            'description' => 'Description',
            'screenshots' => 'Screenshots',
            'seller' => 'Seller',
            'status' => 'Status',
            'starting_price' => 'Starting price',
            'current_price' => 'Highest bid',
            'ends_at' => 'Ends at',
            'edit' => 'Edit auction',
            'cancel' => 'Cancel auction',
            'back' => 'Back to auctions',
        ],

        'create' => [
            'title' => 'Create Auction',
            'subtitle' => 'Save it as a draft, then publish it when ready.',
            'section_details' => 'Auction details',
            'section_price' => 'Price',
            'preview' => 'Listing preview',
            'preview_empty' => 'The main screenshot shows up here',
            'preview_price_empty' => '—',
            'preview_ends_empty' => 'when published',
        ],

        'edit' => [
            'title' => 'Edit Auction',
            'subtitle' => 'Update the auction details before publishing.',
        ],

        'form' => [
            'title' => 'Title',
            'title_placeholder' => 'e.g. Mythic account with 500 skins',
            'description' => 'Description',
            'description_placeholder' => 'Describe the rank, heroes, skins, and other key details.',
            'starting_price' => 'Starting price (IDR)',
            'starting_price_hint' => 'Minimum :amount.',
            'reserve_price' => 'Reserve price (Rp)',
            'reserve_price_hint' => 'Optional. The highest bid must reach this amount before you can close the auction early. Leave empty if you have no minimum.',
            'screenshots' => 'Screenshots',
            'screenshots_hint' => 'Upload 1–:count images. JPG, PNG, or WEBP.',
            'screenshots_add' => 'Add image',
            'screenshots_count' => ':count/:max images',
            'screenshots_remove' => 'Remove image',
            'screenshots_move_left' => 'Move left',
            'screenshots_move_right' => 'Move right',
            'screenshots_drag' => 'Drag to reorder',
            'screenshots_primary' => 'Main',
            'submit' => 'Save draft',
            'save' => 'Save changes',
            'cancel' => 'Cancel',
        ],

        'my' => [
            'title' => 'My Auctions',
            'subtitle' => 'Manage your drafts and active auctions.',
            'empty' => "You don't have any auctions yet.",
            'empty_title' => 'No auctions yet',
            'empty_body' => 'Start selling your first ML account and track its bids here.',
            'create' => 'Create a new auction',
            'publish' => 'Publish',
            'publish_ends_at' => 'Ends at',
            'publish_hint' => 'Minimum :min hours, maximum :max hours from now.',
            'cancel' => 'Cancel',
            'edit' => 'Edit',
            'delete' => 'Delete',
            'status' => 'Status',
            'price' => 'Price',
            'ends_at' => 'Ends',
            'view' => 'View',
        ],
    ],

    'datetime' => [
        'dialog' => 'Pick a date and time',
        'open_short' => 'Change',
        'close' => 'Close',
        'placeholder' => 'Pick a date and time',
        'previous_month' => 'Previous month',
        'next_month' => 'Next month',
        'hour' => 'Hour',
        'minute' => 'Minute',
        'done' => 'Done',
    ],

    'chat' => [
        'index' => [
            'title' => 'Chat',
            'heading' => 'Your conversations',
            'subtitle' => 'Talk directly to a seller or to our admin.',
            'start_support' => 'Chat with Admin',
            'support_hint' => 'The admin holds the payment account and verifies transfers.',
            'empty_title' => 'No conversations yet',
            'empty_body' => 'Start a chat with the admin for payments, or open the private seller chat once you have won.',
            'admin_inbox_title' => 'Admin inbox',
            'admin_inbox_body' => 'Every conversation currently running.',
        ],

        'show' => [
            'support_title' => 'Help & Payment',
            'auction_title' => 'Auction: :title',
            'group_title' => 'Auction & Payment: :title',
            'credentials_title' => 'Credentials: :title',
            'participants' => 'Participants',
            'moderator' => 'Admin',
            'private_hint' => 'No admin',
            'older' => 'Load older messages',
            'newer' => 'Newest messages',
            'empty_title' => 'No messages yet',
            'empty_body' => 'Start the conversation below.',
            'placeholder' => 'Write a message…',
            'send' => 'Send',
            'sending' => 'Sending…',
        ],

        'roles' => [
            'member' => 'Member',
            'seller' => 'Seller',
            'winner' => 'Winner',
            'admin' => 'Admin',
        ],

        'kinds' => [
            'support' => 'Help',
            'group' => 'Auction Group',
            'auction' => 'Credentials',
        ],

        'actions' => [
            'open_group_chat_winner' => 'Chat with Winner',
            'open_group_chat_seller' => 'Chat with Seller',
            'open_credentials_winner' => 'Send Credentials',
            'open_credentials_seller' => 'Receive Credentials',
            'credentials_hint' => 'Only you and the other party can read credentials here, not the admin.',
        ],

        'fields' => [
            'body' => 'message',
        ],

        'payment' => [
            'heading' => 'Payment',
            'intro' => 'The admin holds the funds as an intermediary: the buyer pays the admin, then the admin forwards it to the seller.',
            'bid_label' => 'Winning bid',
            'fee_label' => 'Admin fee',
            'total_label' => 'Total to pay',
            'kinds' => [
                'payment_request' => 'Payment Request',
                'payment_proof' => 'Incoming Receipt',
                'payment_received' => 'Funds Received',
                'transfer_proof' => 'Receipt To Seller',
                'payment_completed' => 'Paid',
            ],
            'steps' => [
                'requested' => 'Waiting for the winner to pay',
                'received' => 'Funds received, admin is forwarding to the seller',
                'transferred' => 'Waiting for the seller to confirm',
                'completed' => 'Payment complete',
            ],
            'qris_title' => 'Scan the QRIS to pay',
            'account_name' => 'Account name',
            'account_number' => 'Account number',
            'waiting' => 'Waiting on',
            'you' => 'You',
            'actions' => [
                'request' => 'Send QRIS & amount',
                'proof' => 'Upload transfer receipt',
                'received' => 'Money has arrived',
                'transfer' => 'Upload receipt for the payout to the seller',
                'confirm' => 'I have received the funds',
            ],
            'hints' => [
                'request' => 'Send the winner the amount due together with the QRIS.',
                'proof' => 'Upload a screenshot of the transfer to the admin account.',
                'received' => 'Check the admin account before confirming.',
                'transfer' => 'Once the funds are in, pay the seller and upload the receipt here.',
                'confirm' => 'Only confirm after the money has actually reached your account.',
            ],
            'no_proof_yet' => 'The winner has not uploaded a transfer receipt yet.',
            'choose_proof' => 'Choose a transfer receipt screenshot',
            'uploading' => 'Uploading…',
            'dialog' => [
                'confirm' => 'Continue',
                'cancel' => 'Cancel',
            ],
            'flash' => [
                'request_sent' => 'The QRIS and amount were sent to the conversation.',
                'received' => 'The funds were confirmed in the admin account.',
                'completed' => 'Payment complete. The auction is marked as paid.',
            ],
            /*
             * Transcript entries written by `CoordinatePayment`. These are the
             * bodies of the structured payment messages, so they read as a
             * sentence from the actor rather than as a label for a control.
             */
            'messages' => [
                'request' => 'Payment request sent. The winner should transfer the total above to the admin account.',
                'proof' => 'Transfer receipt for the admin account was uploaded.',
                'received' => 'The funds were confirmed in the admin account.',
                'transfer' => 'The funds were forwarded to the seller, along with the transfer receipt.',
                'completed' => 'The seller confirmed the funds arrived. The auction is paid.',
            ],
            'fields' => [
                'proof' => 'transfer receipt',
            ],
            'errors' => [
                'proof_required' => 'A transfer receipt is required.',
                'proof_image' => 'The transfer receipt must be an image (JPG, PNG or WebP).',
                'proof_size' => 'The transfer receipt may be at most :max KB.',
                'unavailable' => 'This payment step is not available right now.',
            ],
        ],

        'errors' => [
            'body_required' => 'The message cannot be empty.',
            'body_too_long' => 'The message may be at most :max characters.',
            'no_winner' => 'This auction has no winner yet.',
            'no_chat' => 'You do not have access to this conversation.',
        ],
    ],

];
