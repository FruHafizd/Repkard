<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route di luar panel Filament tidak melewati canAccessPanel(), jadi sesi user yang sudah
 * dinonaktifkan harus ditolak di sini.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->is_active, 403);

        return $next($request);
    }
}
