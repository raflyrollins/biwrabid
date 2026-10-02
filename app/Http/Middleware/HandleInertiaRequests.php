<?php

namespace App\Http\Middleware;

use App\Support\AuctionConfig;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'translations' => fn (): array => (array) trans('ui'),
            'locale' => app()->getLocale(),
            'currency' => AuctionConfig::currency(),
            'flash' => fn (): array => [
                'status' => $request->session()->get('status'),
                // `error` is flashed by the early-close, cancel, chat and payment
                // routes. It used to be dropped here, so those redirects showed
                // the user nothing at all.
                'error' => $request->session()->get('error'),
            ],
        ];
    }
}
