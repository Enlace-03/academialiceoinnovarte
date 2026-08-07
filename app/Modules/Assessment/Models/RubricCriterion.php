<?php

declare(strict_types=1);

namespace App\Modules\Assessment\Models;

use Database\Factories\RubricCriterionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * level_descriptions: JSON keyed por RubricLevel::key (ej. 'inicio',
 * 'en_proceso', ...) — reutiliza los 4 niveles del catálogo global, nunca
 * texto libre por rúbrica.
 */
#[Fillable(['rubric_id', 'name', 'level_descriptions', 'position'])]
class RubricCriterion extends Model
{
    use HasFactory, SoftDeletes;

    protected $casts = [
        'level_descriptions' => 'array',
    ];

    protected static function newFactory(): RubricCriterionFactory
    {
        return RubricCriterionFactory::new();
    }

    public function rubric(): BelongsTo
    {
        return $this->belongsTo(Rubric::class);
    }
}
