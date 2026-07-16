<?php

declare(strict_types=1);

namespace App\Modules\Identity\Policies;

use App\Models\User;

// Autorización de acciones sobre estudiantes (rol student del modelo User).
//
// Patrón de Policies de Identity: cada Policy vive junto a las demás en este
// namespace, pero solo UNA por modelo puede registrarse en Gate::policy()
// (AppServiceProvider) — Laravel no admite más de una policy por clase. Hoy
// ese slot para User::class lo ocupa App\Policies\UserPolicy. StudentPolicy
// NO se registra ahí a propósito: se invoca manualmente (app(StudentPolicy::class))
// desde donde se necesite autorizar acciones específicas sobre estudiantes,
// en vez de competir por el slot de Gate::policy(User::class, ...).
// ProjectPolicy (módulo Project) debe replicar esta misma convención si
// también recae sobre un modelo que ya tiene su policy "dueña" registrada.
final class StudentPolicy
{
    // Ver el listado de estudiantes.
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('students.view');
    }

    // Ver un estudiante concreto.
    public function view(User $user, User $student): bool
    {
        if ($user->hasPermissionTo('students.view')) {
            return true;
        }
        // Un padre puede ver a su propio hijo.
        if ($user->hasRole('parent')) {
            return $user->children()->whereKey($student->id)->exists();
        }
        return false;
    }

    /**
     * Crear un estudiante. Solo permiso pleno students.create.
     *
     * Nota: esta Policy tuvo antes un camino de permiso ACOTADO por grupo
     * (students.create.scoped, vía un modelo UserGrant que nunca llegó a
     * existir en el codebase). Se simplificó a este único camino porque ese
     * subsistema de delegación con alcance no está terminado ni conectado a
     * nada activo hoy. Ver TODO.md para retomarlo si se necesita delegación
     * de "crear estudiante" acotada por grupo.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('students.create');
    }

    public function update(User $user, User $student): bool
    {
        return $user->hasPermissionTo('students.update');
    }
}
