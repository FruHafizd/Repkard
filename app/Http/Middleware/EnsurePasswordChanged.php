<?php

namespace App\Http\Middleware;

use App\Filament\Pages\GantiPassword;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $panel = Filament::getCurrentPanel()->getId();

        if (! $request->user()?->must_change_password
            || $request->routeIs("filament.{$panel}.pages.ganti-password", "filament.{$panel}.auth.logout")) {
            return $next($request);
        }

        return redirect(GantiPassword::getUrl());
    }
}
