<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Models;

use App\Models\User;
use App\Modules\Project\Models\Project;
use Database\Factories\PerformanceSnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Serie de tiempo real (a diferencia de StudentMetric): unique(student_id,
 * project_id, snapshot_date) fija la cadencia a como máximo un snapshot por
 * día -- RecalculateStudentProgressAction hace upsert sobre la fecha de hoy,
 * nunca inserta una fila nueva si ya corrió hoy para ese estudiante+proyecto.
 *
 * metrics (JSON) guarda 'progress_pct' (mecánico) y 'qualitative_level_key'
 * (el key de RubricLevel, ej. 'logro_esperado') como datos SEPARADOS a
 * propósito -- nunca fusionados en un solo número (decisión confirmada del
 * Hito 4: dos indicadores separados, nunca fusionados).
 */
#[Fillable(['student_id', 'project_id', 'snapshot_date', 'metrics'])]
class PerformanceSnapshot extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'metrics' => 'array',
        ];
    }

    protected static function newFactory(): PerformanceSnapshotFactory
    {
        return PerformanceSnapshotFactory::new();
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
