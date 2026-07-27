<?php

declare(strict_types=1);

namespace App\Modules\Institution\Policies;

use App\Models\User;
use App\Modules\Institution\Models\Group;

class GroupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('institution.groups.manage');
    }

    public function view(User $user, Group $group): bool
    {
        return $user->hasPermissionTo('institution.groups.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('institution.groups.manage');
    }

    public function update(User $user, Group $group): bool
    {
        return $user->hasPermissionTo('institution.groups.manage');
    }

    public function delete(User $user, Group $group): bool
    {
        return $user->hasPermissionTo('institution.groups.manage');
    }
}
