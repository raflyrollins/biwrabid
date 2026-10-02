<?php

declare(strict_types=1);

namespace App\Http\Requests\Auction;

use App\Support\AuctionConfig;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAuctionRequest extends FormRequest
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
            'reserve_price' => [
                'nullable',
                'integer',
                'min:'.AuctionConfig::minimumStartingPrice(),
                'gte:starting_price',
            ],
        ];
    }
}
