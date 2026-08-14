<?php

namespace App\Providers\Filament;

use App\Filament\Academic\Livewire\DatabaseNotifications;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AcademicPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('academic')
            ->path('academia')
            ->login()
            ->databaseNotifications(livewireComponent: DatabaseNotifications::class)
            ->colors([
                'primary' => Color::Emerald,
            ])
            // Sin esto, Filament sirve su CSS por defecto -- ninguna clase de
            // Tailwind propia (fuera de las que el paquete ya usa por
            // casualidad) llega a las vistas de este panel. Ver el docblock
            // de resources/css/filament/academic/theme.css.
            ->viteTheme('resources/css/filament/academic/theme.css')
            ->discoverResources(in: app_path('Filament/Academic/Resources'), for: 'App\Filament\Academic\Resources')
            ->discoverPages(in: app_path('Filament/Academic/Pages'), for: 'App\Filament\Academic\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Academic/Widgets'), for: 'App\Filament\Academic\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->authGuard('web');
    }
}