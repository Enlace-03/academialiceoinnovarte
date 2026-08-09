<?php

declare(strict_types=1);

namespace App\Modules\Communication\Listeners;

use App\Modules\Assessment\Events\SubmissionEvaluated;
use App\Modules\Communication\Actions\ResolveStudentNotificationRecipientsAction;
use App\Modules\Communication\Notifications\EvaluationReceived;

/**
 * Correo + plataforma (misma prioridad que los recordatorios de entrega),
 * mismo ruteo por ciclo (ResolveStudentNotificationRecipientsAction):
 * estudiante directo en ciclos 3-4, acudientes en ciclos 1-2.
 */
final class NotifyStudentOfEvaluation
{
    public function handle(SubmissionEvaluated $event): void
    {
        $student = $event->evaluation->submission->student;

        foreach (app(ResolveStudentNotificationRecipientsAction::class)->execute($student) as $recipient) {
            $recipient->notify(new EvaluationReceived($event->evaluation));
        }
    }
}
