<?php

declare(strict_types=1);

namespace App\Livewire\Shared;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
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
 */
#[Layout('layouts.portal')]
class Login extends Component
{
    private const REMEMBER_DURATION_IN_MINUTES = 90 * 24 * 60;

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

        if (! Auth::validate(['email' => $this->email, 'password' => $this->password])) {
            $this->errorMessage = 'Estas credenciales no coinciden con nuestros registros.';

            return;
        }

        $user = User::where('email', $this->email)->firstOrFail();

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

    public function render()
    {
        return view('livewire.shared.login');
    }
}
