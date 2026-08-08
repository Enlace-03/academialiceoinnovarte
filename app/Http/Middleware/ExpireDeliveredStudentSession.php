<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Identity\Actions\EndStudentSessionAction;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cierre automático de seguridad de una sesión entregada (Hito 3b-2): una
 * clase dura ~45-60 minutos, no se justifica una sesión de estudiante
 * abierta por más tiempo sin actividad en el dispositivo. No se toca
 * SESSION_LIFETIME global (afectaría también a personal en /admin y
 * /academia) -- es una verificación activa, propia de este mecanismo,
 * basada en 'active_grant_last_seen_at' (no en el reloj de sesión de
 * Laravel, que no distingue "actividad" de "sesión todavía viva").
 *
 * No-op si no hay una entrega activa en la sesión actual (session()
 * ('active_grant_id') es null para cualquier login normal -- estudiante
 * propio, padre, o personal).
 */
class ExpireDeliveredStudentSession
{
    private const MAX_IDLE_MINUTES = 50;

    public function handle(Request $request, Closure $next): Response
    {
        $grantId = $request->session()->get('active_grant_id');

        if ($grantId === null) {
            return $next($request);
        }

        $lastSeenAt = $request->session()->get('active_grant_last_seen_at');

        // abs(): diffInMinutes() no garantiza signo positivo según la
        // dirección de la comparación (varía entre versiones de Carbon) --
        // aquí solo importa la magnitud del tiempo transcurrido.
        $minutesIdle = $lastSeenAt !== null
            ? abs(Carbon::parse($lastSeenAt)->diffInMinutes(now()))
            : 0;

        if ($minutesIdle > self::MAX_IDLE_MINUTES) {
            app(EndStudentSessionAction::class)->execute($grantId);

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        $request->session()->put('active_grant_last_seen_at', now()->toISOString());

        return $next($request);
    }
}
