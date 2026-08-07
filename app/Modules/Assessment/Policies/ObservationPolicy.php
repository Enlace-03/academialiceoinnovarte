<?php

declare(strict_types=1);

namespace App\Modules\Assessment\Policies;

use App\Models\User;
use App\Modules\Assessment\Models\Observation;

/**
 * "own" se define por Observation::teacher_id — mismo patrón own/all que
 * ProjectPolicy. observations.view.all es de solo lectura (coordinator hoy
 * solo ve, no escribe observaciones de otros).
 */
class ObservationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('observations.view.all')
            || $user->hasPermissionTo('observations.write.own')
            || $user->hasPermissionTo('observations.write.all');
    }

    public function view(User $user, Observation $observation): bool
    {
        return $user->hasPermissionTo('observations.view.all')
            || $user->hasPermissionTo('observations.write.all')
            || ($user->hasPermissionTo('observations.write.own') && $observation->teacher_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('observations.write.own')
            || $user->hasPermissionTo('observations.write.all');
    }

    public function update(User $user, Observation $observation): bool
    {
        return $user->hasPermissionTo('observations.write.all')
            || ($user->hasPermissionTo('observations.write.own') && $observation->teacher_id === $user->id);
    }

    public function delete(User $user, Observation $observation): bool
    {
        return $this->update($user, $observation);
    }
}
