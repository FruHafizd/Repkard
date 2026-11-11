<?php

namespace App\Http\Middleware;

use App\Filament\Member\Pages\LengkapiProfil;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMemberProfileComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Belum verifikasi email: biarkan Filament mengarahkan ke halaman verifikasi.
        if (! $user?->hasVerifiedEmail() || $request->routeIs(
            'filament.member.pages.lengkapi-profil',
            'filament.member.pages.ganti-password',
            'filament.member.auth.*',
        )) {
            return $next($request);
        }

        $member = $user->member;

        if ($member && (blank($member->gender) || blank($member->photo_path))) {
            return redirect(LengkapiProfil::getUrl());
        }

        return $next($request);
    }
}
