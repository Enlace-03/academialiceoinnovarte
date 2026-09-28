<?php

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
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
