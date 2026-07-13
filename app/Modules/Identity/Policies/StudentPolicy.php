<?php

declare(strict_types=1);

namespace App\Modules\Identity\Policies;

use App\Models\User;
use App\Modules\Identity\Models\UserGrant;

// Autorización de acciones sobre estudiantes.
// Caso especial: crear estudiantes puede estar ACOTADO por alcance (scope).
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
     * Crear un estudiante en un grupo determinado.
     * Tres caminos posibles:
     *  1. Permiso pleno students.create → cualquier grupo.
     *  2. Permiso acotado students.create.scoped con scope "all" → cualquier grupo.
     *  3. Permiso acotado students.create.scoped con scope "groups" → solo esos grupos.
     */
    public function create(User $user, ?int $targetGroupId = null): bool
    {
        // Camino 1: permiso pleno (admin, rector, secretary).
        if ($user->hasPermissionTo('students.create')) {
            return true;
        }

        // Caminos 2 y 3: permiso acotado (profesor con concesión del rector).
        if (! $user->hasPermissionTo('students.create.scoped')) {
            return false;
        }

        $grant = UserGrant::where('user_id', $user->id)
            ->where('permission', 'students.create.scoped')
            ->first();

        if (! $grant || ! is_array($grant->scope)) {
            return false;
        }

        // Alcance "todos los estudiantes de la institución".
        if (($grant->scope['type'] ?? null) === 'all') {
            return true;
        }

        // Alcance limitado a ciertos grupos.
        if (($grant->scope['type'] ?? null) === 'groups') {
            if ($targetGroupId === null) {
                return false; // se requiere saber el grupo destino para decidir
            }
            return in_array($targetGroupId, $grant->scope['group_ids'] ?? [], true);
        }

        return false;
    }

    public function update(User $user, User $student): bool
    {
        return $user->hasPermissionTo('students.update');
    }
}
