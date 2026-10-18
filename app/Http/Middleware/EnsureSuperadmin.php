<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperadmin
{
    /**
     * Handle an incoming request.
     */
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $user = $request->user();

        if ($user === null) {
            abort(401, 'Silakan login terlebih dahulu.');
        }

        if (!$user->isSuperadmin()) {
            abort(403, 'Akses hanya untuk superadmin.');
        }

        return $next($request);
    }
}
