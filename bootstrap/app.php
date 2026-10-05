<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\ExpireDeliveredStudentSession;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Spatie/laravel-permission ya no auto-registra este alias para
        // Laravel 11+ (solo expone macros de ruta que dependen de que
        // exista) — lo registramos para el grupo de rutas del portal de
        // estudiante (Hito 3b-1).
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'expire-delivered-session' => ExpireDeliveredStudentSession::class,
        ]);

        // Global (no solo grupo 'web'): los paneles Filament arman su propio
        // stack de middleware. Ver el docblock de SecurityHeaders.
        $middleware->append(SecurityHeaders::class);

        // Grupo 'web', no global: necesita la sesión (StartSession va en
        // 'web'). Ver el docblock de EnsureUserIsActive.
        $middleware->web(append: [EnsureUserIsActive::class]);

        // Un invitado que entra a la raíz ('/') ve la bienvenida; cualquier
        // otra ruta protegida lo manda al login, como siempre.
        $middleware->redirectGuestsTo(
            fn (Request $request) => $request->is('/') ? route('welcome') : route('login'),
        );

        // Confiar SOLO en 127.0.0.1 como proxy (Hito de demo con Cloudflare
        // Quick Tunnel, temporal): cloudflared corre local y reenvia a
        // Apache por loopback en texto plano -- sin esto, Laravel arma
        // redirects/URLs absolutas (route(), url()) con el Host y esquema
        // http de la conexion real (localhost), no con el hostname publico
        // https del tunel, así que cualquier redirect (ej. tras el login)
        // termina apuntando a http://academialiceoinnovarte.test, que no
        // resuelve fuera de esta máquina. Confiando solo en 127.0.0.1 (no
        // '*') esto no abre nada a que un cliente externo falsifique estas
        // cabeceras -- cloudflared es el único que puede alcanzar Apache
        // por esa IP. Inofensivo para el desarrollo local normal (sin
        // túnel no hay X-Forwarded-* que leer, este bloque no cambia nada).
        //
        // El valor '127.0.0.1' está afinado específicamente para esta
        // topología local (cloudflared -> Apache por loopback en la misma
        // máquina) -- REVISAR en el primer deploy real a cPanel, donde el
        // proxy real (si lo hay) puede tener otra IP, o el hosting puede no
        // necesitar esto en absoluto. Ver TODO.md.
        $middleware->trustProxies(at: ['127.0.0.1']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
