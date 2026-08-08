<?php

namespace App\Models;

use App\Models\Concerns\HasDelegationCeiling;
use App\Modules\Identity\Models\ParentStudent;
use App\Modules\Institution\Models\Group;
use App\Modules\Institution\Models\SchoolGrade;
use App\Modules\Project\Models\Project;
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

#[Fillable(['name', 'email', 'password', 'group_id', 'school_grade_id', 'document_number', 'is_active'])]
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

    public function schoolGrade(): BelongsTo
    {
        return $this->belongsTo(SchoolGrade::class, 'school_grade_id');
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
     * Un estudiante accede a un proyecto si su grado (school_grade) pertenece
     * al mismo ciclo que el proyecto — un proyecto es "del ciclo", no de un
     * grupo puntual, así que todos los grados de ese ciclo comparten acceso.
     * Sin grado asignado, no hay acceso (fail-closed). Reutilizable por
     * Community (foro/chat) y por cualquier otra pantalla de estudiante.
     */
    public function canAccessProject(Project $project): bool
    {
        return $this->schoolGrade !== null
            && $this->schoolGrade->cycle_id === $project->cycle_id;
    }

    /**
     * Panel-level access gate. 'admin' is restricted to super_admin and to
     * anyone holding a permission whose name starts with one of the prefixes
     * in config('permissions.admin_panel_permission_prefixes') (users.* /
     * institution.* today). Checked by prefix, not by role name, so a future
     * role with those permissions needs no change here. If the config is
     * empty or missing, only super_admin passes (fail-closed). 'academic'
     * requires at least one role categorized as "staff" in
     * config('permissions.role_categories') — student/parent (category
     * "identity") are fail-closed out, since they now have their own real
     * login path at "/" (Hito 3b-0). Per-resource Policies still do the real
     * restriction within the panel for staff roles.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'admin' => $this->isSuperAdmin()
                || $this->hasAnyPermissionStartingWith(
                    config('permissions.admin_panel_permission_prefixes', [])
                ),
            'academic' => $this->isStaff(),
            default => false,
        };
    }

    /**
     * Personal (cualquier rol de categoría "staff"): rector, coordinator,
     * secretary, teacher, super_admin. Usado por Policies que necesitan una
     * puerta de "es alguien del colegio" antes de decidir con más precisión
     * (ej. ChatMessagePolicy, ante la ausencia de teacher_assignments real).
     */
    public function isStaff(): bool
    {
        return $this->hasAnyRoleInCategory('staff');
    }

    protected function hasAnyPermissionStartingWith(array $prefixes): bool
    {
        return $this->getAllPermissions()
            ->pluck('name')
            ->contains(fn (string $permission): bool => Str::startsWith($permission, $prefixes));
    }

    protected function hasAnyRoleInCategory(string $category): bool
    {
        $rolesInCategory = collect(config('permissions.role_categories', []))
            ->filter(fn (string $roleCategory) => $roleCategory === $category)
            ->keys();

        return $this->roles()->whereIn('name', $rolesInCategory)->exists();
    }
}
