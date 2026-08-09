<?php

declare(strict_types=1);

namespace App\Modules\Communication\Listeners;

use App\Modules\Assessment\Events\SubmissionRegistered;
use Filament\Notifications\Notification;

/**
 * En plataforma, sin correo (decisión confirmada del Hito 5a): usa el
 * sistema nativo de notificaciones de Filament directamente
 * (Notification::make()->sendToDatabase()), no una clase Notification
 * propia -- Filament::DatabaseNotifications filtra por data->format ===
 * 'filament' (ver vendor/filament/notifications/src/Livewire/
 * DatabaseNotifications.php), así que una notificación estándar de
 * Illuminate nunca aparece en su campanita aunque quede bien guardada en
 * la tabla notifications (confirmado en verificación manual del Hito 5a).
 * Notifica al docente con autoridad del proyecto de la evidencia entregada
 * (Project::createdBy, mismo criterio que ProjectPolicy).
 */
final class NotifyTeacherOfNewSubmission
{
    public function handle(SubmissionRegistered $event): void
    {
        $submission = $event->submission;
        $teacher = $submission->expectedEvidence->phase->project->createdBy;

        if ($teacher === null || ! $teacher->isStaff()) {
            return;
        }

        Notification::make()
            ->title('Nueva entrega registrada')
            ->body("{$submission->student->name} entregó una evidencia.")
            ->sendToDatabase($teacher);
    }
}
