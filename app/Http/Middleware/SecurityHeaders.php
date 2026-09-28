<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras de seguridad básicas para TODA respuesta de la app. Registrado
 * como middleware global en bootstrap/app.php, no solo en el grupo 'web':
 * los paneles Filament (/admin, /academia) arman su propio stack de
 * middleware y no pasarían por un middleware agregado solo al grupo web.
 *
 * HSTS solo en producción y sobre HTTPS real -- en local (WampServer por
 * http) o detrás del Quick Tunnel de demo, fijarlo haría que el navegador
 * exija HTTPS para ese host durante un año. Sin CSP por ahora: Livewire,
 * Alpine y Filament necesitan una política afinada (scripts inline, eval de
 * Alpine) que se define aparte.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        if (app()->isProduction() && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
