<?php

declare(strict_types=1);

namespace App\Modules\Assessment\Policies;

use App\Models\User;
use App\Modules\Assessment\Models\Rubric;

/**
 * Banco de rúbricas reutilizable — sin distinción own/all, cualquiera con
 * rubrics.manage administra cualquier rúbrica (no están atadas a un proyecto).
 */
class RubricPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('rubrics.manage');
    }

    public function view(User $user, Rubric $rubric): bool
    {
        return $user->hasPermissionTo('rubrics.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('rubrics.manage');
    }

    public function update(User $user, Rubric $rubric): bool
    {
        return $user->hasPermissionTo('rubrics.manage');
    }

    public function delete(User $user, Rubric $rubric): bool
    {
        return $user->hasPermissionTo('rubrics.manage');
    }
}
