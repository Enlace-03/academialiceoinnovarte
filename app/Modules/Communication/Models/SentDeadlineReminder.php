<?php

declare(strict_types=1);

namespace App\Modules\Communication\Models;

use App\Modules\Project\Models\StudentPhaseSchedule;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Marca de idempotencia (Hito 5b): una fila por (schedule, umbral) basta
 * para saber que ese recordatorio ya se envió, sin importar cuántos
 * destinatarios recibió (estudiante directo o varios acudientes) ni cuántas
 * veces corra el job ese día. Ver unique(student_phase_schedule_id,
 * threshold_days) en la migración.
 */
#[Fillable(['student_phase_schedule_id', 'threshold_days', 'sent_at'])]
class SentDeadlineReminder extends Model
{
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function studentPhaseSchedule(): BelongsTo
    {
        return $this->belongsTo(StudentPhaseSchedule::class);
    }
}
