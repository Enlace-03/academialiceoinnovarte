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

        if (! self::isPurePoll($request)) {
            $request->session()->put('active_grant_last_seen_at', now()->toISOString());
        }

        return $next($request);
    }

    /**
     * Un wire:poll sin expresión (los tres del proyecto: notification-bell,
     * group-chat, private-chat-panel) dispara $wire.$commit() del lado del
     * cliente, que viaja SIN 'calls' (array vacío) y SIN 'updates' (vacío) --
     * verificado contra el JS real de Livewire (wireProperty('$commit', ...)
     * / Commit.toRequestPayload()), no contra la forma asumida de
     * calls:[{method:'$refresh'}] (esa sí ocurre si alguien escribe
     * wire:poll="$refresh" explícito, así que también se cubre por si acaso).
     * Sin este chequeo, un sondeo periódico sin actividad real del usuario
     * mantendría 'active_grant_last_seen_at' fresco para siempre y la sesión
     * entregada nunca expiraría mientras la pestaña siguiera abierta.
     */
    private static function isPurePoll(Request $request): bool
    {
        $components = $request->input('components');

        if (! is_array($components) || $components === []) {
            return false;
        }

        foreach ($components as $component) {
            if (! empty($component['updates'] ?? [])) {
                return false;
            }

            foreach ($component['calls'] ?? [] as $call) {
                if (($call['method'] ?? null) !== '$refresh') {
                    return false;
                }
            }
        }

        return true;
    }
}
