<?php

declare(strict_types=1);

namespace App\Livewire\Shared;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Login mínimo del panel fuera de Filament (Hito 3b-0). No es específico de
 * rol — cualquier usuario autenticable por el guard web pasa por aquí; la
 * autorización de qué puede ver después vive en las Policies de cada
 * recurso, no en este formulario. Sin recuperación de contraseña ni
 * registro (ver TODO.md: única vía de recuperación es gestión manual desde
 * /admin por secretaría).
 *
 * Persistencia de sesión por rol: parent siempre queda "recordado" por
 * REMEMBER_DURATION_IN_MINUTES (90 días — deliberadamente acortado respecto
 * a los ~400 días por defecto de Laravel, dado que la cuenta puede acceder a
 * datos de un menor); cualquier otro rol usa sesión estándar sin persistencia
 * extendida.
 *
 * Límite de intentos: MAX_FAILED_ATTEMPTS fallos por DECAY_SECONDS, por
 * pareja correo+IP (no solo IP -- en un colegio muchos estudiantes salen
 * por la misma IP pública, y bloquear la IP entera dejaría a un salón sin
 * poder entrar por culpa de uno). Solo cuentan los fallos; un login exitoso
 * limpia el contador. El bloqueo aplica incluso con la contraseña correcta.
 */
#[Layout('layouts.portal')]
class Login extends Component
{
    private const REMEMBER_DURATION_IN_MINUTES = 90 * 24 * 60;

    private const MAX_FAILED_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    public string $email = '';

    public string $password = '';

    public string $errorMessage = '';

    public function login(): void
    {
        $this->errorMessage = '';

        $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = $this->throttleKey();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_FAILED_ATTEMPTS)) {
            $this->errorMessage = 'Demasiados intentos. Intenta de nuevo en '.RateLimiter::availableIn($throttleKey).' segundos.';

            return;
        }

        if (! Auth::validate(['email' => $this->email, 'password' => $this->password])) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

            $this->errorMessage = 'Estas credenciales no coinciden con nuestros registros.';

            return;
        }

        RateLimiter::clear($throttleKey);

        $user = User::where('email', $this->email)->firstOrFail();

        // Cuenta desactivada desde /admin: la contraseña es correcta, pero no
        // se inicia sesión. EnsureUserIsActive corta además cualquier sesión
        // ya abierta (incluida la cookie "recordarme" de acudientes).
        if (! $user->is_active) {
            $this->errorMessage = 'Tu cuenta está desactivada. Comunícate con secretaría.';

            return;
        }

        Auth::guard('web')->setRememberDuration(self::REMEMBER_DURATION_IN_MINUTES);

        Auth::login($user, remember: $user->hasRole('parent'));

        session()->regenerate();

        // Respeta url.intended (guardada automáticamente por Laravel cuando
        // un middleware bloqueó a un usuario no autenticado, ej. al hacer
        // clic en el enlace de una notificación sin sesión activa) -- antes
        // de este fix siempre aterrizaba en portal.home, perdiendo el
        // destino real (segunda vuelta del Hito 5).
        //
        // Sin navigate:true a propósito -- Livewire resetea el scroll al
        // completar una navegación SPA, ganándole a scrollIntoView() del
        // script de layouts/portal.blade.php que restaura el #fragmento
        // (#fase-{id}) perdido en url.intended. Recarga completa aquí es la
        // única forma confiable de que el scroll a la fase funcione; mismo
        // bug ya encontrado y resuelto en NotificationBell::visit().
        $this->redirect(session()->pull('url.intended', route('portal.home')));
    }

    private function throttleKey(): string
    {
        return 'portal-login:'.Str::lower($this->email).'|'.request()->ip();
    }

    public function render()
    {
        return view('livewire.shared.login');
    }
}
