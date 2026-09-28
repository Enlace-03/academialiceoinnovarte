<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Identity\Actions\EndStudentSessionAction;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Corta la sesión de un usuario desactivado desde /admin (users.is_active)
 * en su siguiente petición, sin esperar a que la sesión expire sola --
 * incluida la cookie "recordarme" de 90 días de los acudientes, que de otro
 * modo seguiría reautenticándolo.
 *
 * Registrado al final del grupo 'web' (no como global): los globales corren
 * antes de StartSession y no ven la sesión ni al usuario autenticado. Cubre
 * el portal y POST /livewire/update (que también va por 'web'). Los paneles
 * Filament arman su propio stack sin el grupo 'web'; ahí el corte lo hace
 * User::canAccessPanel(), que exige is_active y Filament re-evalúa en cada
 * petición del panel.
 *
 * Si la sesión era una entrega de sesión de estudiante, se cierra la
 * entrega igual que en /logout, para que no quede abierta en el registro.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && ! Auth::user()->is_active) {
            $grantId = $request->session()->get('active_grant_id');

            if ($grantId !== null) {
                app(EndStudentSessionAction::class)->execute($grantId);
            }

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        return $next($request);
    }
}
