<?php

declare(strict_types=1);

namespace App\Modules\Project\Models;

use Database\Factories\ExpectedEvidenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * type: archivo | texto | participación en foro — catálogo simple, no ENUM de BD.
 *
 * alternative_group implementa productos alternativos: varias evidencias de la
 * misma fase que comparten el mismo valor no-nulo son mutuamente excluyentes
 * (el estudiante/equipo entrega una, no todas). Si es null, es obligatoria por
 * sí sola, sin alternativa.
 */
#[Fillable(['phase_id', 'type', 'description', 'is_required', 'alternative_group'])]
class ExpectedEvidence extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * "evidence" es sustantivo incontable en inglés: el inflector de Eloquent
     * no lo pluraliza, así que el nombre de tabla adivinado por convención
     * ("expected_evidence") no coincide con la tabla real ("expected_evidences").
     */
    protected $table = 'expected_evidences';

    public const TYPES = [
        'archivo' => 'Archivo',
        'texto' => 'Texto',
        'participacion_foro' => 'Participación en foro',
    ];

    protected $casts = [
        'is_required' => 'boolean',
    ];

    protected static function newFactory(): ExpectedEvidenceFactory
    {
        return ExpectedEvidenceFactory::new();
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function phase(): BelongsTo
    {
        return $this->belongsTo(Phase::class);
    }

    /**
     * Las demás evidencias de la misma fase con el mismo alternative_group
     * (excluyéndose a sí misma). Vacío si alternative_group es null.
     */
    public function alternatives(): Collection
    {
        if ($this->alternative_group === null) {
            return new Collection();
        }

        return static::query()
            ->where('phase_id', $this->phase_id)
            ->where('alternative_group', $this->alternative_group)
            ->where('id', '!=', $this->id)
            ->get();
    }
}
