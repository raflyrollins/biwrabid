<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the admin area.
 *
 * Until this existed `is_admin` was written by the seeder and the registration
 * test but never read by application logic. Every admin route now declares the
 * `admin` alias instead of repeating the check.
 */
final class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->is_admin) {
            abort(403);
        }

        // Makes `Auth::user()` available inside admin Blade/Inertia views that
        // run outside the web middleware's usual guarantees.
        Auth::shouldUse('web');

        return $next($request);
    }
}
