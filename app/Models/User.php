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

#[Fillable([
    'name', 'email', 'password', 'group_id', 'school_grade_id', 'document_number', 'is_active',
    'photo_disk', 'photo_path', 'photo_upload_blocked',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable, HasUuids, HasRoles, HasDelegationCeiling;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'photo_upload_blocked' => 'boolean',
        ];
    }

    public function hasPhoto(): bool
    {
        return $this->photo_path !== null;
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
     * Extraído de la duplicación real encontrada en UserPolicy (viewPhoto()
     * e isEligibleGuardianForPhoto(), ambas con el mismo whereKey()->exists())
     * y en PortalHome (uploadPhoto()/removePhoto()) -- único punto de verdad
     * para "¿es este usuario acudiente de este estudiante?", reutilizado por
     * Policies y por la verificación explícita a mano en cada componente del
     * drill-down del acudiente (defensa en profundidad, mismo patrón que
     * ForumThreadShow/GroupChat con hasRole('student')).
     */
    public function isGuardianOf(User $student): bool
    {
        return $this->children()->whereKey($student->id)->exists();
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
     * Ciclos 1-2 (Exploratorio, Conceptual): único punto de verdad para
     * decidir estrellas vs. barra de avance en el portal (Hito de estrellas)
     * -- consultado desde cada vista de estudiante/acudiente que hoy muestra
     * progress_pct, nunca duplicado por vista. Deliberadamente NO reutilizado
     * por PortalHome::canManagePhoto (mismo umbral de ciclo hoy, pero es una
     * regla de negocio distinta -- elegibilidad de subida de foto, no
     * representación visual de avance -- que podría divergir de este umbral
     * en el futuro sin relación alguna).
     *
     * Sin grado asignado, no es ciclo temprano (fail-closed hacia la barra
     * numérica, nunca asume "primaria" sin dato real).
     */
    public function isInEarlyCycle(): bool
    {
        $cycleOrder = $this->schoolGrade?->cycle?->order;

        return $cycleOrder !== null && $cycleOrder <= 2;
    }

    /**
     * Panel-level access gate. 'admin' is restricted to super_admin and to
     * anyone holding a permission whose name starts with one of the prefixes
     * in config('permissions.admin_panel_permission_prefixes') (users.* /
     * institution.* / students.create today -- the last one added so the
     * narrow students.create path into UserPolicy::create() is actually
     * reachable, see that Policy). Checked by prefix, not by role name, so a future
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
