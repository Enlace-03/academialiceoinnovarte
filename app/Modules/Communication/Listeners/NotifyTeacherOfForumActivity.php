<?php

declare(strict_types=1);

namespace App\Modules\Communication\Listeners;

use App\Models\User;
use App\Modules\Communication\Support\FormatsStudentLabel;
use App\Modules\Community\Events\ForumPostCreated;
use Filament\Notifications\Notification;

/**
 * En plataforma, sin correo (decisión confirmada del Hito 5a): usa el
 * sistema nativo de notificaciones de Filament directamente (mismo motivo
 * que NotifyTeacherOfNewSubmission -- ver su docblock). Notifica al docente
 * que creó el hilo y/o al que tiene autoridad del proyecto
 * (Project::createdBy) -- ambos si son personas distintas, ninguno si
 * coincide con quien publicó, y solo si son personal (isStaff()): un hilo
 * puede haberlo creado un estudiante (ForumThreadPolicy::create() lo
 * permite), y ese caso no debe notificarse a sí mismo como si fuera
 * docente.
 */
final class NotifyTeacherOfForumActivity
{
    use FormatsStudentLabel;

    public function handle(ForumPostCreated $event): void
    {
        $post = $event->post;
        $thread = $post->thread;

        collect([$thread->creator, $thread->project->createdBy])
            ->filter()
            ->unique('id')
            ->filter(fn (User $user): bool => $user->isStaff() && $user->id !== $post->user_id)
            ->each(fn (User $teacher) => Notification::make()
                ->title('Nueva actividad en el foro')
                ->body("{$this->studentLabel($post->user)} publicó en \"{$thread->title}\".")
                ->sendToDatabase($teacher));
    }
}
