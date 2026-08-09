<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Listeners;

use App\Modules\Community\Events\ForumPostLiked;
use App\Modules\Tracking\Jobs\RecalculateStudentProgressJob;
use App\Modules\Tracking\Models\LearningEvent;

/**
 * Se registra en learning_events y dispara recálculo por consistencia con
 * los demás eventos, aunque hoy forum_participations (el conteo que sí
 * pesa en progress_pct) solo cuenta posts, no likes -- el recálculo suele
 * no cambiar el número, pero el evento igual queda en el log de auditoría.
 */
final class RecordForumPostLiked
{
    public function handle(ForumPostLiked $event): void
    {
        $project = $event->post->thread->project;

        LearningEvent::create([
            'student_id' => $event->user->id,
            'project_id' => $project->id,
            'event_type' => 'forum_post_liked',
            'payload' => ['forum_post_id' => $event->post->id],
            'occurred_at' => now(),
        ]);

        RecalculateStudentProgressJob::dispatch($event->user, $project);
    }
}
