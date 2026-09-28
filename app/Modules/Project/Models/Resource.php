<?php

declare(strict_types=1);

namespace App\Modules\Project\Models;

use Database\Factories\ResourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * type: pdf | video | enlace — catálogo simple, no ENUM de BD.
 */
#[Fillable(['phase_id', 'guide_id', 'title', 'type', 'url_or_path'])]
class Resource extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public const TYPES = [
        'pdf' => 'PDF',
        'video' => 'Video',
        'enlace' => 'Enlace',
    ];

    protected static function newFactory(): ResourceFactory
    {
        return ResourceFactory::new();
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function phase(): BelongsTo
    {
        return $this->belongsTo(Phase::class);
    }

    public function guide(): BelongsTo
    {
        return $this->belongsTo(Guide::class);
    }

    /**
     * Defensa en profundidad para las vistas (project-show de estudiante y
     * de padre): url_or_path no tenía NINGUNA validación de esquema antes
     * de este fix, así que puede existir un registro viejo con
     * 'javascript:' u otro esquema no-web -- las vistas usan esto antes de
     * renderizar un <a href> clicable.
     */
    public function hasHttpUrl(): bool
    {
        return str_starts_with($this->url_or_path, 'http://') || str_starts_with($this->url_or_path, 'https://');
    }
}
