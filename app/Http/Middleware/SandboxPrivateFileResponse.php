<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rutas que sirven archivos subidos por usuarios desde el disco privado
 * (fotos de galería/foro, adjuntos de entrega, documentos de
 * retroalimentación del docente, foto de perfil): si alguien
 * abre el archivo directo en el navegador, 'default-src none; sandbox'
 * impide que ejecute scripts o cargue nada, aunque el archivo fuera un SVG
 * o un HTML disfrazado. Defensa en profundidad sobre la validación de tipo
 * en la subida -- no la reemplaza. Como <img src>, la imagen se sigue
 * mostrando igual: la CSP del archivo no afecta a la página que lo embebe.
 */
class SandboxPrivateFileResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Content-Security-Policy', "default-src 'none'; sandbox");

        return $response;
    }
}
