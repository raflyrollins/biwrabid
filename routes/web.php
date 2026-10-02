<?php

use App\Http\Controllers\AuctionController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\BidController;
use App\Http\Controllers\Chat\ChatRoomController;
use App\Http\Controllers\Chat\MessageController;
use App\Http\Controllers\Chat\PaymentController;
use App\Http\Controllers\Chat\StartChatController;
use App\Http\Controllers\Seller\AuctionController as SellerAuctionController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::get('auctions', [AuctionController::class, 'index'])->name('auctions.index');

Route::middleware('guest')->group(function (): void {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store']);
    Route::get('register', [RegisterController::class, 'create'])->name('register');
    Route::post('register', [RegisterController::class, 'store']);
});

Route::middleware('auth')->group(function (): void {
    Route::post('logout', LogoutController::class)->name('logout');

    Route::get('my-auctions', [SellerAuctionController::class, 'index'])->name('my-auctions.index');
    Route::get('auctions/create', [SellerAuctionController::class, 'create'])->name('auctions.create');
    Route::post('auctions', [SellerAuctionController::class, 'store'])->name('auctions.store');
    Route::get('auctions/{auction}/edit', [SellerAuctionController::class, 'edit'])->name('auctions.edit');
    Route::put('auctions/{auction}', [SellerAuctionController::class, 'update'])->name('auctions.update');
    Route::delete('auctions/{auction}', [SellerAuctionController::class, 'destroy'])->name('auctions.destroy');
    Route::post('auctions/{auction}/publish', [SellerAuctionController::class, 'publish'])->name('auctions.publish');
    Route::post('auctions/{auction}/cancel', [SellerAuctionController::class, 'cancel'])->name('auctions.cancel');
    Route::post('auctions/{auction}/end-early', [SellerAuctionController::class, 'endEarly'])->name('auctions.end-early');
    Route::post('auctions/{auction}/bids', [BidController::class, 'store'])->name('auctions.bids.store');

    // Chat rooms are bound by uuid, so the placeholder is snake_case while the
    // URL stays /chat/{uuid}.
    Route::get('chat', [ChatRoomController::class, 'index'])->name('chat.index');
    Route::post('chat/support', [StartChatController::class, 'support'])->name('chat.support');

    // Two threads per auction, two endpoints on purpose: which room a caller
    // may open is a property of the room kind, so the kind is named by the
    // route rather than by a request field.
    Route::post('chat/auctions/{auction}/group', [StartChatController::class, 'group'])
        ->name('chat.auction.group');
    Route::post('chat/auctions/{auction}/credentials', [StartChatController::class, 'credentials'])
        ->name('chat.auction.credentials');
    Route::get('chat/{chat_room}', [ChatRoomController::class, 'show'])->name('chat.show');
    Route::post('chat/{chat_room}/messages', [MessageController::class, 'store'])->name('chat.messages.store');

    // Payment happens inside the auction's group thread: the admin sends the
    // QRIS, the winner and then the admin each attach a transfer receipt, and
    // the seller closes it out. Five endpoints for five steps, because each one
    // has a different actor and the `reply` policy alone cannot tell them apart.
    Route::prefix('chat/{chat_room}/payment')->name('chat.payment.')->group(function (): void {
        Route::post('request', [PaymentController::class, 'storeRequest'])->name('request');
        Route::post('proof', [PaymentController::class, 'storeProof'])->name('proof');
        Route::post('received', [PaymentController::class, 'markReceived'])->name('received');
        Route::post('transfer', [PaymentController::class, 'storeTransferProof'])->name('transfer');
        Route::post('confirm', [PaymentController::class, 'confirm'])->name('confirm');
    });
});

Route::get('auctions/{auction}', [AuctionController::class, 'show'])->name('auctions.show');
