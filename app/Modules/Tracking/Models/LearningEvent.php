<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Models;

use App\Models\User;
use App\Modules\Project\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sobre la tabla learning_events, particionada por rango mensual y creada
 * con SQL crudo (ver migración 2027_01_01_000050) -- PK compuesta
 * (id, occurred_at), SIN foreign keys de verdad (regla absoluta #3 del
 * proyecto: la integridad es responsabilidad de la app, nunca de la BD,
 * en esta tabla específica). student()/project() son conveniencia de
 * consulta, no constraints -- MySQL/MariaDB no permite FKs reales sobre
 * una tabla particionada.
 *
 * Log inmutable: sin updated_at (solo occurred_at), sin softDeletes.
 * Nunca usar Schema::table() ni ->update() masivo sobre esta tabla.
 */
class LearningEvent extends Model
{
    protected $table = 'learning_events';

    public $timestamps = false;

    protected $fillable = ['student_id', 'project_id', 'event_type', 'payload', 'occurred_at'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'datetime',
        ];
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
