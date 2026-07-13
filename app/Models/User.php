<?php

namespace App\Models;

use App\Models\Concerns\HasDelegationCeiling;
use App\Modules\Identity\Models\ParentStudent;
use App\Modules\Institution\Models\Group;
use App\Modules\Institution\Models\SchoolGrade;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'group_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable, HasUuids, HasRoles, HasDelegationCeiling;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function schoolGrade(): ?SchoolGrade
    {
        return $this->group?->schoolGrade;
    }

    public function children(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'parent_student', 'parent_id', 'student_id')
            ->using(ParentStudent::class)
            ->withPivot(['relationship', 'is_primary_contact'])
            ->withTimestamps();
    }

    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'parent_student', 'student_id', 'parent_id')
            ->using(ParentStudent::class)
            ->withPivot(['relationship', 'is_primary_contact'])
            ->withTimestamps();
    }

    /**
     * Panel-level access gate. 'admin' is restricted to super_admin and to
     * anyone holding a permission whose name starts with one of the prefixes
     * in config('permissions.admin_panel_permission_prefixes') (users.* /
     * institution.* today). Checked by prefix, not by role name, so a future
     * role with those permissions needs no change here. If the config is
     * empty or missing, only super_admin passes (fail-closed). 'academic' is
     * open to any user with at least one role assigned; per-resource Policies
     * do the real restriction there once resources exist.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'admin' => $this->isSuperAdmin()
                || $this->hasAnyPermissionStartingWith(
                    config('permissions.admin_panel_permission_prefixes', [])
                ),
            'academic' => $this->roles()->exists(),
            default => false,
        };
    }

    protected function hasAnyPermissionStartingWith(array $prefixes): bool
    {
        return $this->getAllPermissions()
            ->pluck('name')
            ->contains(fn (string $permission): bool => Str::startsWith($permission, $prefixes));
    }
}
