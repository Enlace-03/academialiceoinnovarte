<?php

declare(strict_types=1);

namespace App\Modules\Assessment\Models;

use App\Models\User;
use Database\Factories\EvaluationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * unique(submission_id, evaluator_type): una entrega admite más de una
 * evaluación, una por tipo (config('assessment.evaluator_types')) — hoy solo
 * 'teacher' está en uso; 'self'/'peer' quedan preparados sin construirse.
 */
#[Fillable(['submission_id', 'evaluated_by', 'evaluator_type', 'feedback', 'evaluated_at'])]
class Evaluation extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $casts = [
        'evaluated_at' => 'datetime',
    ];

    protected static function newFactory(): EvaluationFactory
    {
        return EvaluationFactory::new();
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function evaluatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluated_by');
    }

    public function results(): HasMany
    {
        return $this->hasMany(EvaluationResult::class);
    }

    /**
     * Nivel consolidado = moda de los niveles de sus EvaluationResult.
     * Empate → gana el nivel más bajo (criterio conservador, no el más
     * favorable). Insumo para Tracking (progreso/boletín) más adelante —
     * este hito solo lo calcula, no lo expone en ningún reporte todavía.
     */
    public function consolidatedLevel(): ?RubricLevel
    {
        $counts = $this->results()
            ->selectRaw('rubric_level_id, COUNT(*) as total')
            ->groupBy('rubric_level_id')
            ->pluck('total', 'rubric_level_id');

        if ($counts->isEmpty()) {
            return null;
        }

        $maxCount = $counts->max();
        $tiedLevelIds = $counts->filter(fn (int $count): bool => $count === $maxCount)->keys();

        return RubricLevel::whereIn('id', $tiedLevelIds)->orderBy('order')->first();
    }
}
