<?php

declare(strict_types=1);

namespace App\Modules\Communication\Actions;

use App\Modules\Assessment\Models\Submission;
use App\Modules\Communication\Models\SentDeadlineReminder;
use App\Modules\Communication\Notifications\SubmissionDeadlineReminder;
use App\Modules\Project\Models\StudentPhaseSchedule;
use Illuminate\Support\Carbon;

/**
 * Corazón del Hito 5b. Umbrales fijos (3 y 1 día antes de
 * student_phase_schedules.end_date), pensado para correr una vez al día vía
 * el scheduler (routes/console.php). Idempotente por diseño:
 * SentDeadlineReminder(schedule, threshold) es único en BD, así que una
 * segunda corrida el mismo día no reenvía nada aunque el comando en sí no
 * sepa si ya corrió hoy.
 *
 * Ruteo (decisión confirmada): estudiante en ciclos 3-4 (Cycle::order >= 3)
 * recibe directo (correo + plataforma); en ciclos 1-2, cada acudiente
 * vinculado recibe en su lugar (solo correo, ver docblock de
 * SubmissionDeadlineReminder::via()). Criterio extraído a
 * ResolveStudentNotificationRecipientsAction (segunda vuelta del Hito 5),
 * reutilizado también por la notificación de evaluación recibida.
 *
 * Salta el envío si el estudiante ya entregó TODAS las evidencias esperadas
 * de la fase -- recordarle una entrega que ya hizo sería un defecto visible
 * del propio recordatorio, no un caso hipotético a evitar por exceso de
 * cautela.
 */
final class SendSubmissionDeadlineRemindersAction
{
    private const THRESHOLDS = [3, 1];

    public function execute(?Carbon $today = null): int
    {
        $today = ($today ?? now())->startOfDay();
        $sentCount = 0;

        foreach (self::THRESHOLDS as $thresholdDays) {
            $targetDate = $today->copy()->addDays($thresholdDays)->toDateString();

            $schedules = StudentPhaseSchedule::query()
                ->whereDate('end_date', $targetDate)
                ->with(['student.schoolGrade.cycle', 'student.guardians', 'phase.expectedEvidences'])
                ->get();

            foreach ($schedules as $schedule) {
                if ($this->allEvidencesSubmitted($schedule)) {
                    continue;
                }

                $alreadySent = SentDeadlineReminder::query()
                    ->where('student_phase_schedule_id', $schedule->id)
                    ->where('threshold_days', $thresholdDays)
                    ->exists();

                if ($alreadySent) {
                    continue;
                }

                $this->notifyRecipients($schedule, $thresholdDays);

                SentDeadlineReminder::create([
                    'student_phase_schedule_id' => $schedule->id,
                    'threshold_days' => $thresholdDays,
                    'sent_at' => now(),
                ]);

                $sentCount++;
            }
        }

        return $sentCount;
    }

    private function allEvidencesSubmitted(StudentPhaseSchedule $schedule): bool
    {
        $expectedIds = $schedule->phase->expectedEvidences->pluck('id');

        if ($expectedIds->isEmpty()) {
            return false;
        }

        $submittedIds = Submission::query()
            ->where('student_id', $schedule->student_id)
            ->whereIn('expected_evidence_id', $expectedIds)
            ->whereIn('status', ['submitted', 'evaluated'])
            ->pluck('expected_evidence_id');

        return $expectedIds->diff($submittedIds)->isEmpty();
    }

    private function notifyRecipients(StudentPhaseSchedule $schedule, int $thresholdDays): void
    {
        foreach (app(ResolveStudentNotificationRecipientsAction::class)->execute($schedule->student) as $recipient) {
            $recipient->notify(new SubmissionDeadlineReminder($schedule, $thresholdDays));
        }
    }
}
