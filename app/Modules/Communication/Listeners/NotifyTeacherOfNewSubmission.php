<?php

declare(strict_types=1);

namespace App\Modules\Communication\Listeners;

use App\Modules\Assessment\Events\SubmissionRegistered;
use App\Modules\Communication\Support\FormatsStudentLabel;
use Filament\Actions\Action;
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
 *
 * Destino (segunda vuelta): la página de edición del proyecto padre, NO una
 * entrega puntual -- Filament no ofrece una URL propia por fila de un
 * RelationManager (confirmado: ExpectedEvidencesRelationManager solo abre
 * modales, ProjectResource no tiene página 'view'). `?relation={índice}`
 * existe para preseleccionar una pestaña, pero es una clave posicional
 * frágil (depende del orden de ProjectResource::getRelations()) -- no vale
 * la pena para una URL que queda guardada permanentemente en notifications.
 */
final class NotifyTeacherOfNewSubmission
{
    use FormatsStudentLabel;

    public function handle(SubmissionRegistered $event): void
    {
        $submission = $event->submission;
        $project = $submission->expectedEvidence->phase->project;
        $teacher = $project->createdBy;

        if ($teacher === null || ! $teacher->isStaff()) {
            return;
        }

        Notification::make()
            ->title('Nueva entrega registrada')
            ->body("{$this->studentLabel($submission->student)} entregó una evidencia.")
            ->actions([
                Action::make('view')->label('Ver proyecto')->url(
                    route('filament.academic.resources.projects.edit', ['record' => $project->id]),
                ),
            ])
            ->sendToDatabase($teacher);
    }
}
