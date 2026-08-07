<?php

declare(strict_types=1);

namespace App\Modules\Project\Actions;

use App\Models\User;
use App\Modules\Project\Models\Phase;
use App\Modules\Project\Models\ProjectTeam;
use App\Modules\Project\Models\StudentPhaseSchedule;
use Illuminate\Support\Facades\DB;

/**
 * Único punto de entrada para modificar el cronograma de un estudiante por
 * fase. Nunca usar StudentPhaseSchedule::update() directo: esta Action
 * garantiza dos reglas de negocio confirmadas:
 *
 * 1. extension_count se incrementa cada vez que end_date cambia después de
 *    la primera vez que se guardó (señal de riesgo, no solo el % de avance).
 * 2. Si el estudiante pertenece a un ProjectTeam de ese proyecto, la misma
 *    fecha se propaga a todos sus compañeros de equipo en la misma
 *    transacción (los tiempos son iguales para la rúbrica).
 */
final class UpdateStudentPhaseScheduleAction
{
    public function execute(User $student, Phase $phase, string $startDate, string $endDate): StudentPhaseSchedule
    {
        return DB::transaction(function () use ($student, $phase, $startDate, $endDate) {
            $schedule = $this->upsertForStudent($student, $phase, $startDate, $endDate);

            $this->propagateToTeammates($student, $phase, $startDate, $endDate);

            return $schedule;
        });
    }

    private function upsertForStudent(User $student, Phase $phase, string $startDate, string $endDate): StudentPhaseSchedule
    {
        $schedule = StudentPhaseSchedule::firstOrNew([
            'student_id' => $student->id,
            'phase_id' => $phase->id,
        ]);

        $endDateChanged = $schedule->exists && $schedule->end_date?->toDateString() !== $endDate;

        if (! $schedule->exists) {
            $schedule->extension_count = 0;
        }

        $schedule->start_date = $startDate;
        $schedule->end_date = $endDate;

        if ($endDateChanged) {
            $schedule->extension_count++;
        }

        $schedule->save();

        return $schedule;
    }

    private function propagateToTeammates(User $student, Phase $phase, string $startDate, string $endDate): void
    {
        $team = ProjectTeam::query()
            ->where('project_id', $phase->project_id)
            ->whereHas('users', fn ($query) => $query->where('users.id', $student->id))
            ->first();

        if ($team === null) {
            return;
        }

        $teammates = $team->users()->where('users.id', '!=', $student->id)->get();

        foreach ($teammates as $teammate) {
            $this->upsertForStudent($teammate, $phase, $startDate, $endDate);
        }
    }
}
