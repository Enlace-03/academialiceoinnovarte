<?php

declare(strict_types=1);

namespace App\Modules\Project\Models;

use Database\Factories\PhaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Las 4 fases institucionales son fijas y siempre en el mismo orden.
 * Se generan automáticamente al crear un Project (ver ProjectObserver);
 * el nombre por defecto es este catálogo, pero queda editable después.
 */
#[Fillable(['project_id', 'name', 'order', 'description'])]
class Phase extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public const INSTITUTIONAL_NAMES = [
        1 => 'Activación',
        2 => 'Exploración',
        3 => 'Investigación, análisis y reflexión',
        4 => 'Creación del producto final',
    ];

    protected static function newFactory(): PhaseFactory
    {
        return PhaseFactory::new();
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function guides(): HasMany
    {
        return $this->hasMany(Guide::class);
    }

    public function resources(): HasMany
    {
        return $this->hasMany(Resource::class);
    }

    public function expectedEvidences(): HasMany
    {
        return $this->hasMany(ExpectedEvidence::class);
    }
}
