<?php

declare(strict_types=1);

namespace App\Modules\Assessment\Models;

use Database\Factories\EvaluationResultFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['evaluation_id', 'rubric_criterion_id', 'rubric_level_id', 'comment'])]
class EvaluationResult extends Model
{
    use HasFactory;

    protected static function newFactory(): EvaluationResultFactory
    {
        return EvaluationResultFactory::new();
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }

    public function rubricCriterion(): BelongsTo
    {
        return $this->belongsTo(RubricCriterion::class);
    }

    public function rubricLevel(): BelongsTo
    {
        return $this->belongsTo(RubricLevel::class);
    }
}
