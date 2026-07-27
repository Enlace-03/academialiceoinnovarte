<?php

declare(strict_types=1);

namespace App\Modules\Institution\Policies;

use App\Models\User;
use App\Modules\Institution\Models\Cycle;

// Ciclos: estructura institucional estable, CRUD simple bajo un único permiso.
class CyclePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('institution.cycles.manage');
    }

    public function view(User $user, Cycle $cycle): bool
    {
        return $user->hasPermissionTo('institution.cycles.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('institution.cycles.manage');
    }

    public function update(User $user, Cycle $cycle): bool
    {
        return $user->hasPermissionTo('institution.cycles.manage');
    }

    public function delete(User $user, Cycle $cycle): bool
    {
        return $user->hasPermissionTo('institution.cycles.manage');
    }
}
