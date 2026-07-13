<?php

namespace App\Models\Concerns;

use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Adds to User the "delegation ceiling" logic, tailored to the actual model
 * from config/permissions.php + RolePermissionSeeder: the preset roles
 * (super_admin, rector, coordinator, secretary, teacher) are real Spatie
 * roles that carry an already-defined bundle of permissions. student/parent
 * are fixed roles with no permissions from the catalog. On top of their
 * role(s), a user can have additional individual permissions.
 */
trait HasDelegationCeiling
{
    public const SUPER_ADMIN_ROLE = 'super_admin';

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(self::SUPER_ADMIN_ROLE);
    }

    /**
     * Can this user view/edit the individual permissions section of other
     * users? Relies on the 'users.grant' permission from the catalog.
     * (Preset-role assignment does NOT depend on this, only on the
     * assignableRoles() filter.)
     */
    public function canGrantPermissions(): bool
    {
        return $this->isSuperAdmin() || $this->can('users.grant');
    }

    /**
     * Roles this user can assign to others: a role is delegable if the set
     * of permissions it grants is a subset of the permissions the acting
     * user already has. student/parent (with no permissions) are
     * automatically available to anyone. super_admin can only be assigned
     * by another super_admin.
     */
    public function assignableRoles(): Collection
    {
        if ($this->isSuperAdmin()) {
            return Role::query()->get();
        }

        $ownPermissions = $this->getAllPermissions()->pluck('name');

        return Role::with('permissions')->get()
            ->filter(function (Role $role) use ($ownPermissions) {
                if ($role->name === self::SUPER_ADMIN_ROLE) {
                    return false;
                }

                return $role->permissions->pluck('name')->diff($ownPermissions)->isEmpty();
            })
            ->values();
    }

    /**
     * Names of permissions this user can delegate to others as additional
     * individual permissions: they can only grant permissions they
     * themselves have (direct or inherited from their roles). super_admin
     * can grant any permission from the catalog.
     */
    public function assignablePermissionNames(): array
    {
        if ($this->isSuperAdmin()) {
            return Permission::pluck('name')->all();
        }

        return $this->getAllPermissions()->pluck('name')->all();
    }

    /**
     * Can this user manage (edit/deactivate/delete) the target user?
     * Rule: you cannot manage someone whose permission set exceeds your own
     * (they could have authority you don't have), nor a super_admin, unless
     * you yourself are a super_admin.
     */
    public function canManageUser(self $target): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($this->is($target)) {
            return true;
        }

        if ($target->isSuperAdmin()) {
            return false;
        }

        $targetPermissions = $target->getAllPermissions()->pluck('name');
        $ownPermissions = $this->getAllPermissions()->pluck('name');

        return $targetPermissions->diff($ownPermissions)->isEmpty();
    }
}
