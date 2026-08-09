<?php

declare(strict_types=1);

namespace App\Modules\Communication\Notifications;

use App\Modules\Assessment\Models\Evaluation;
use App\Modules\Communication\Support\FormatsStudentLabel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Disparada por SubmissionEvaluated (segunda vuelta del Hito 5) -- cada
 * evaluación nueva (incluida una reevaluación tras "devuelta", que
 * actualiza la misma fila de Evaluation via updateOrCreate) dispara su
 * propia notificación, sin deduplicar como si fuera el mismo evento
 * repetido: son juicios de valor distintos, no un reenvío accidental.
 *
 * Nivel cualitativo vía Evaluation::consolidatedLevel()->label -- NUNCA el
 * 'order' numérico (regla absoluta #4). Mismo criterio de ruteo/canal que
 * SubmissionDeadlineReminder: estudiante en ciclos 3-4 recibe directo
 * (correo+plataforma), acudiente en ciclos 1-2 recibe en su lugar (solo
 * correo) -- ver ResolveStudentNotificationRecipientsAction.
 */
final class EvaluationReceived extends Notification implements ShouldQueue
{
    use Queueable, FormatsStudentLabel;

    public function __construct(public readonly Evaluation $evaluation) {}

    public function via(object $notifiable): array
    {
        return $notifiable->hasRole('student') ? ['mail', 'database'] : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $student = $this->evaluation->submission->student;
        $teacherName = $this->evaluation->evaluatedBy->name;
        $level = $this->evaluation->consolidatedLevel()?->label ?? 'sin nivel asignado';
        $project = $this->evaluation->submission->expectedEvidence->phase->project;
        $isStudent = $notifiable->hasRole('student');

        $body = $isStudent
            ? "{$teacherName} evaluó tu entrega en \"{$project->title}\". Nivel: {$level}."
            : "{$teacherName} evaluó la entrega de {$this->studentLabel($student)} en \"{$project->title}\". Nivel: {$level}.";

        return (new MailMessage())
            ->subject('Evaluación recibida — Liceo Innovarte')
            ->greeting('Hola '.($isStudent ? $student->name : $notifiable->name))
            ->line($body)
            ->line('Ingresa a la plataforma para ver la retroalimentación completa.')
            ->salutation('Saludos, Academia Liceo Innovarte');
    }

    public function toArray(object $notifiable): array
    {
        $student = $this->evaluation->submission->student;

        return [
            'type' => 'evaluation_received',
            'evaluation_id' => $this->evaluation->id,
            'submission_id' => $this->evaluation->submission_id,
            'teacher_name' => $this->evaluation->evaluatedBy->name,
            'level_label' => $this->evaluation->consolidatedLevel()?->label,
            'project_title' => $this->evaluation->submission->expectedEvidence->phase->project->title,
            'student_label' => $this->studentLabel($student),
        ];
    }
}
