<?php

declare(strict_types=1);

namespace App\Modules\Assessment\Models;

use App\Modules\Institution\Models\Institution;
use Database\Factories\RubricFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Banco de rúbricas reutilizable entre proyectos — decisión confirmada del
 * Hito 2: Rubric NO se ata a Project ni a Phase. Se vincula a un uso puntual
 * indirectamente vía ExpectedEvidence::rubric_id.
 */
#[Fillable(['institution_id', 'name', 'description'])]
class Rubric extends Model
{
    use HasFactory, SoftDeletes;

    protected static function newFactory(): RubricFactory
    {
        return RubricFactory::new();
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function criteria(): HasMany
    {
        return $this->hasMany(RubricCriterion::class)->orderBy('position');
    }
}
