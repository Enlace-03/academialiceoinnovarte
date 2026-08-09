<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Listeners;

use App\Modules\Community\Events\ChatMessageSent;
use App\Modules\Project\Models\Project;
use App\Modules\Tracking\Jobs\RecalculateStudentProgressJob;
use App\Modules\Tracking\Models\LearningEvent;

/**
 * chat_messages no tiene project_id (TODO.md #16, decisión ya confirmada) --
 * el registro crudo en learning_events queda con project_id NULL (la
 * columna ya es nullable exactamente para este caso). Para el recálculo sí
 * hace falta un proyecto: como el chat es del grupo completo, no de un
 * proyecto puntual, se recalcula para TODOS los proyectos del ciclo del
 * autor (no eliminados) -- mismo criterio de elegibilidad que
 * RecalculateAllProgressJob.
 */
final class RecordChatMessageSent
{
    public function handle(ChatMessageSent $event): void
    {
        $message = $event->message;
        $author = $message->user;

        LearningEvent::create([
            'student_id' => $message->user_id,
            'project_id' => null,
            'event_type' => 'chat_message_sent',
            'payload' => [
                'chat_message_id' => $message->id,
                'group_id' => $message->group_id,
            ],
            'occurred_at' => now(),
        ]);

        if ($author->schoolGrade === null) {
            return;
        }

        $projects = Project::query()
            ->where('cycle_id', $author->schoolGrade->cycle_id)
            ->get();

        foreach ($projects as $project) {
            RecalculateStudentProgressJob::dispatch($author, $project);
        }
    }
}
