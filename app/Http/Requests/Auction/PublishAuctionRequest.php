<?php

declare(strict_types=1);

namespace App\Http\Requests\Auction;

use App\Support\AuctionConfig;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class PublishAuctionRequest extends FormRequest
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
            'ends_at' => [
                'required',
                'date',
                'after:'.Carbon::now()
                    ->addHours(AuctionConfig::minimumDurationHours())
                    ->toDateTimeString(),
                'before:'.Carbon::now()
                    ->addHours(AuctionConfig::maximumDurationHours())
                    ->toDateTimeString(),
            ],
        ];
    }
}
