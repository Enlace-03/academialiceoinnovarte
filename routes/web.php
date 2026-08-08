<?php

use App\Livewire\Shared\Login;
use App\Livewire\Shared\PortalHome;
use App\Livewire\Student\ForumThreadShow;
use App\Livewire\Student\GroupChat;
use App\Livewire\Student\MyProjects;
use App\Livewire\Student\ProjectShow;
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

/**
 * Portal de estudiante (Hito 3b-1). role:student, no parent — el padre se
 * queda en el placeholder de PortalHome hasta que se diseñe su propio
 * dashboard (fuera de alcance de este hito). Los modelos se resuelven por
 * la columna 'uuid' (regla absoluta #5: nunca IDs autoincrementales en URLs
 * de estudiantes), no por la 'id' interna.
 */
Route::middleware(['auth', 'role:student'])->group(function () {
    Route::get('/mis-proyectos', MyProjects::class)->name('student.projects.index');
    Route::get('/mis-proyectos/{project:uuid}', ProjectShow::class)->name('student.projects.show');
    /**
     * withoutScopedBindings(): dos parámetros con :campo explícito en la
     * misma ruta activan el scoping automático de Laravel (intenta resolver
     * 'thread' como hijo de 'project' vía una relación adivinada por
     * convención de nombre — 'threads()', que no existe; el modelo real es
     * forumThreads()). Se resuelve 'thread' de forma independiente por su
     * propio uuid; el chequeo de que pertenezca al {project} de la URL se
     * hace a mano en ForumThreadShow::mount() (404 si no coincide).
     */
    Route::get('/mis-proyectos/{project:uuid}/foro/{thread:uuid}', ForumThreadShow::class)
        ->name('student.forum.show')
        ->withoutScopedBindings();
    Route::get('/mi-grupo/chat', GroupChat::class)->name('student.chat');
});
