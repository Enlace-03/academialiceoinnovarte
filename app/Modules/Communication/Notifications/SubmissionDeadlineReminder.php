<?php

declare(strict_types=1);

namespace App\Modules\Communication\Notifications;

use App\Modules\Project\Models\StudentPhaseSchedule;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Enviada a 3 y 1 día antes de student_phase_schedules.end_date (Hito 5b).
 * Ruteo (SendSubmissionDeadlineRemindersAction): estudiante en ciclos 3-4
 * recibe esta misma notificación directa (correo + plataforma); en ciclos
 * 1-2 la reciben los acudientes en su lugar -- solo por correo, vía via(),
 * porque el dashboard mínimo del acudiente (paso 0 de este hito) no tiene
 * campanita útil todavía más allá de su propia lista de pendientes.
 */
final class SubmissionDeadlineReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly StudentPhaseSchedule $schedule,
        public readonly int $thresholdDays,
    ) {}

    public function via(object $notifiable): array
    {
        return $notifiable->hasRole('student') ? ['mail', 'database'] : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $student = $this->schedule->student;
        $phaseName = $this->schedule->phase->name;
        $dueDate = $this->schedule->end_date->format('d/m/Y');
        $isStudent = $notifiable->hasRole('student');

        $body = $isStudent
            ? "Tu entrega de la fase \"{$phaseName}\" vence en {$this->thresholdDays} día(s), el {$dueDate}."
            : "La entrega de {$student->name} para la fase \"{$phaseName}\" vence en {$this->thresholdDays} día(s), el {$dueDate}.";

        return (new MailMessage())
            ->subject('Recordatorio de entrega — Liceo Innovarte')
            ->greeting('Hola '.($isStudent ? $student->name : $notifiable->name))
            ->line($body)
            ->line('Ingresa a la plataforma para revisar los detalles.')
            ->salutation('Saludos, Academia Liceo Innovarte');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'submission_deadline_reminder',
            'student_id' => $this->schedule->student_id,
            'phase_id' => $this->schedule->phase_id,
            'phase_name' => $this->schedule->phase->name,
            'due_date' => $this->schedule->end_date->toDateString(),
            'threshold_days' => $this->thresholdDays,
        ];
    }
}
