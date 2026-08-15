<?php

declare(strict_types=1);

namespace App\Modules\Communication\Listeners;

use App\Models\User;
use App\Modules\Communication\Notifications\PrivateChatMessageReceived;
use App\Modules\Communication\Support\FormatsStudentLabel;
use App\Modules\Community\Events\PrivateChatMessageSent;
use App\Modules\Community\Models\PrivateChatThread;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Notifica a los DEMÁS participantes del hilo, nunca al propio autor: el
 * otro lado de la conversación individual (estudiante o docente), o todos
 * los integrantes del equipo + el docente responsable para la de equipo.
 * Sin routeo a acudientes (a diferencia de EvaluationReceived/
 * SubmissionDeadlineReminder) -- ver docblock de PrivateChatMessageReceived.
 *
 * "Docente responsable" = Project::createdBy, mismo criterio que
 * NotifyTeacherOfForumActivity/NotifyTeacherOfNewSubmission. Deep link al
 * lado de personal: la página 'view' de ProjectResource (mismo criterio de
 * NotifyTeacherOfNewSubmission -- Filament no ofrece una URL propia por fila
 * de RelationManager, ver su docblock).
 */
final class NotifyPrivateChatParticipants
{
    use FormatsStudentLabel;

    public function handle(PrivateChatMessageSent $event): void
    {
        $message = $event->message;
        $thread = $message->thread;
        $author = $message->user;

        foreach ($this->studentRecipients($thread, $author) as $student) {
            $student->notify(new PrivateChatMessageReceived($message));
        }

        $teacher = $thread->project->createdBy;

        if ($teacher !== null && $teacher->isStaff() && $teacher->id !== $author->id) {
            Notification::make()
                ->title('Nuevo mensaje de chat privado')
                ->body("{$this->studentLabel($author)} escribió en \"{$thread->project->title}\".")
                ->actions([
                    Action::make('view')->label('Ver proyecto')->url(
                        route('filament.academic.resources.projects.view', ['record' => $thread->project_id]),
                    ),
                ])
                ->sendToDatabase($teacher);
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function studentRecipients(PrivateChatThread $thread, User $author): Collection
    {
        if ($thread->type === 'individual') {
            return $thread->student !== null && $thread->student->id !== $author->id
                ? collect([$thread->student])
                : collect();
        }

        if ($thread->team === null) {
            return collect();
        }

        return $thread->team->users()->where('users.id', '!=', $author->id)->get();
    }
}
