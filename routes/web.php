<?php

use App\Livewire\Shared\Login;
use App\Livewire\Shared\PortalHome;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Panel fuera de Filament (Hito 3b-0): login mínimo compartido, guard web
// estándar de Laravel. No colisiona con /admin ni /academia — Filament
// gestiona sus propias rutas/middleware de panel, pero comparte el mismo
// guard 'web', así que la sesión es una sola en todo el sitio; cada panel
// decide qué mostrar según canAccessPanel()/Policies, no según un guard
// distinto.
Route::get('/login', Login::class)->middleware('guest')->name('login');

Route::post('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('login');
})->middleware('auth')->name('logout');

Route::get('/', PortalHome::class)->middleware('auth')->name('portal.home');
