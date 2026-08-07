<?php

declare(strict_types=1);

namespace App\Modules\Assessment\Models;

use Database\Factories\RubricLevelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catálogo global de niveles (sin institution_id), sembrado por
 * RubricLevelSeeder. Escala vigente: Inicio → En proceso → Logro esperado →
 * Logro destacado. NUNCA mostrar `order` al usuario — solo `label`/`color`.
 */
#[Fillable(['key', 'label', 'color', 'order'])]
class RubricLevel extends Model
{
    use HasFactory;

    protected static function newFactory(): RubricLevelFactory
    {
        return RubricLevelFactory::new();
    }

    public function evaluationResults(): HasMany
    {
        return $this->hasMany(EvaluationResult::class);
    }
}
