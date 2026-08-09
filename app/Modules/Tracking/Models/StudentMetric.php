<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Models;

use App\Models\User;
use App\Modules\Project\Models\Project;
use Database\Factories\StudentMetricFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una fila "estado actual" por (student_id, project_id) -- se sobreescribe
 * en cada recálculo, no es serie de tiempo (para eso está PerformanceSnapshot).
 *
 * risk_level/risk_score son de Prediction, no de Tracking (decisión
 * confirmada del Hito 4): RecalculateStudentProgressAction nunca los toca,
 * quedan en su default ('low' / 0) hasta que exista el módulo Prediction.
 *
 * avg_rubric_value queda en null a propósito en este hito: es una columna
 * numérica que representaría un promedio de niveles de rúbrica -- exactamente
 * el tipo de número que la filosofía cualitativa del colegio prohíbe exponer
 * (regla absoluta #4). El indicador cualitativo real vive aparte, en
 * PerformanceSnapshot.metrics, como nivel dominante (moda), nunca como
 * promedio numérico redondeado.
 */
#[Fillable([
    'student_id', 'project_id', 'progress_pct', 'avg_rubric_value',
    'weekly_pace', 'last_active_at', 'inactive_days',
    'risk_level', 'risk_score',
])]
class StudentMetric extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'last_active_at' => 'datetime',
        ];
    }

    protected static function newFactory(): StudentMetricFactory
    {
        return StudentMetricFactory::new();
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
