<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * NOTE: this policy assumes 'users.view', 'users.create', 'users.update'
     * and 'users.delete' permissions defined in your PermissionSeeder.
     * Adjust the names if you already use a different convention.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('users.view');
    }

    public function view(User $user, User $target): bool
    {
        return $user->can('users.view');
    }

    public function create(User $user): bool
    {
        return $user->can('users.create');
    }

    public function update(User $user, User $target): bool
    {
        return $user->can('users.update') && $user->canManageUser($target);
    }

    public function delete(User $user, User $target): bool
    {
        return $user->can('users.delete')
            && $user->canManageUser($target)
            && ! $user->is($target);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('users.delete');
    }
}
