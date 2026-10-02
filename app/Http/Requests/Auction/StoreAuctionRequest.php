<?php

declare(strict_types=1);

namespace App\Http\Requests\Auction;

use App\Support\AuctionConfig;
use Illuminate\Foundation\Http\FormRequest;

class StoreAuctionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:5000'],
            'starting_price' => [
                'required',
                'integer',
                'min:'.AuctionConfig::minimumStartingPrice(),
            ],
            // Optional floor that lets the seller stop bidding early without
            // handing the account to a first-minute lowball bidder. A reserve
            // below the opening price could never be met, so it is rejected.
            'reserve_price' => [
                'nullable',
                'integer',
                'min:'.AuctionConfig::minimumStartingPrice(),
                'gte:starting_price',
            ],
            'screenshots' => [
                'required',
                'array',
                'min:1',
                'max:'.AuctionConfig::maxScreenshots(),
            ],
            'screenshots.*' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:'.AuctionConfig::maxScreenshotSizeKb(),
            ],
        ];
    }
}
