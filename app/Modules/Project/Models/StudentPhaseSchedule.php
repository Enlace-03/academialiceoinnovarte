<?php

declare(strict_types=1);

namespace App\Modules\Project\Models;

use App\Models\User;
use Database\Factories\StudentPhaseScheduleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fechas reales del cronograma de un estudiante para una fase, distintas de
 * las fechas sugeridas del proyecto. extension_count es la señal de riesgo:
 * ver App\Modules\Project\Actions\UpdateStudentPhaseScheduleAction, que es el
 * único punto de entrada para modificarlas (incrementa el contador y propaga
 * la fecha a los compañeros de equipo — nunca usar ->update() directo).
 */
#[Fillable(['student_id', 'phase_id', 'start_date', 'end_date', 'extension_count'])]
class StudentPhaseSchedule extends Model
{
    use HasFactory;

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'extension_count' => 'integer',
    ];

    protected static function newFactory(): StudentPhaseScheduleFactory
    {
        return StudentPhaseScheduleFactory::new();
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function phase(): BelongsTo
    {
        return $this->belongsTo(Phase::class);
    }
}
