<?php

namespace App\Providers\Filament;

use App\Filament\Member\Pages\Auth\EditProfile;
use App\Filament\Member\Pages\Auth\Register;
use App\Filament\Pages\GantiPassword;
use App\Http\Middleware\EnsureMemberProfileComplete;
use App\Http\Middleware\EnsurePasswordChanged;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class MemberPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('member')
            ->path('member')
            ->viteTheme('resources/css/filament/member/theme.css')
            ->login()
            ->registration(Register::class)
            ->emailVerification()
            ->passwordReset()
            ->profile(EditProfile::class)
            ->colors([
                'primary' => config('design.primary'),
                'success' => config('design.success'),
                'danger' => config('design.danger'),
                'gray' => Color::Slate,
            ])
            ->font(config('design.font.sans'), url: config('design.font.url'))
            ->brandLogo(fn (): View => view('partials.wordmark'))
            ->brandLogoHeight('1.75rem')
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): View => view('partials.design-tokens', ['fonts' => false]))
            ->discoverResources(in: app_path('Filament/Member/Resources'), for: 'App\Filament\Member\Resources')
            ->discoverPages(in: app_path('Filament/Member/Pages'), for: 'App\Filament\Member\Pages')
            ->pages([
                Dashboard::class,
                GantiPassword::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Member/Widgets'), for: 'App\Filament\Member\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                EnsurePasswordChanged::class,
                EnsureMemberProfileComplete::class,
            ]);
    }
}
