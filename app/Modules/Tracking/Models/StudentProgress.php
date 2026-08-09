<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Models;

use App\Models\User;
use App\Modules\Project\Models\Phase;
use App\Modules\Project\Models\Project;
use Database\Factories\StudentProgressFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una fila por (student_id, project_id, phase_id) -- phase_id null es la
 * fila "del proyecto completo" (unique lo garantiza). progress_pct es
 * SIEMPRE mecánico (avance de cobertura: evidencias/foro/chat), nunca
 * deriva de la calidad de las evaluaciones -- ese es el indicador
 * cualitativo aparte (ver PerformanceSnapshot.metrics['qualitative_level_key']
 * y RecalculateStudentProgressAction).
 *
 * guides_completed/guides_total quedan en 0 a propósito (Hito 4, base): no
 * existe hoy ningún mecanismo de la app que registre "el estudiante leyó/
 * completó esta guía" -- la columna está en el esquema desde el scaffolding
 * original, pero poblarla con un número inventado sería peor que dejarla
 * vacía. No forma parte de los pesos de la fórmula (evidencias/foro/chat).
 */
#[Fillable([
    'student_id', 'project_id', 'phase_id', 'progress_pct',
    'guides_completed', 'guides_total',
    'forum_participations', 'chat_participations',
    'evidences_submitted', 'evidences_total',
    'status', 'started_at', 'completed_at',
])]
class StudentProgress extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function newFactory(): StudentProgressFactory
    {
        return StudentProgressFactory::new();
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function phase(): BelongsTo
    {
        return $this->belongsTo(Phase::class);
    }
}
