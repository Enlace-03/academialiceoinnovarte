<?php

declare(strict_types=1);

namespace App\Modules\Project\Models;

use App\Models\User;
use App\Modules\Community\Models\ForumPost;
use App\Modules\Community\Models\ForumThread;
use App\Modules\Institution\Models\Cycle;
use App\Modules\Institution\Models\ThinkingField;
use App\Modules\Tracking\Models\StudentProgress;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'cycle_id', 'created_by_user_id', 'title', 'problem_situation',
    'guiding_question', 'purpose', 'semester', 'year',
    'suggested_duration_weeks', 'expected_impact',
])]
class Project extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected static function newFactory(): ProjectFactory
    {
        return ProjectFactory::new();
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(Cycle::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function thinkingFields(): BelongsToMany
    {
        return $this->belongsToMany(ThinkingField::class, 'project_thinking_field');
    }

    public function phases(): HasMany
    {
        return $this->hasMany(Phase::class)->orderBy('order');
    }

    public function teams(): HasMany
    {
        return $this->hasMany(ProjectTeam::class);
    }

    public function forumThreads(): HasMany
    {
        return $this->hasMany(ForumThread::class);
    }

    /**
     * Vista plana de participación entre todos los hilos del proyecto —
     * mismo patrón que expectedEvidences() (hasManyThrough soporta un solo
     * intermedio; ForumPost no pertenece a Project directamente).
     */
    public function forumPosts(): HasManyThrough
    {
        return $this->hasManyThrough(ForumPost::class, ForumThread::class);
    }

    /**
     * Conveniencia para el panel de administración: el cronograma de
     * StudentPhaseSchedule vive en Phase, no en Project directamente.
     */
    public function studentPhaseSchedules(): HasManyThrough
    {
        return $this->hasManyThrough(StudentPhaseSchedule::class, Phase::class);
    }

    /**
     * Conveniencia para el panel de administración: las evidencias esperadas
     * viven en Phase, no en Project directamente. Hito 2: es el punto de
     * entrada para gestionar entregas y evaluación por evidencia.
     */
    public function expectedEvidences(): HasManyThrough
    {
        return $this->hasManyThrough(ExpectedEvidence::class, Phase::class);
    }

    /**
     * Solo las filas "de proyecto completo" (phase_id null) -- Tracking
     * (Hito 4) también guarda una fila por fase, pero esa vista agregada
     * por estudiante en /academia solo necesita el resumen del proyecto.
     */
    public function studentProgress(): HasMany
    {
        return $this->hasMany(StudentProgress::class)->whereNull('phase_id');
    }
}
