<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Listeners;

use App\Modules\Community\Events\ForumPostUnliked;
use App\Modules\Tracking\Jobs\RecalculateStudentProgressJob;
use App\Modules\Tracking\Models\LearningEvent;

final class RecordForumPostUnliked
{
    public function handle(ForumPostUnliked $event): void
    {
        $project = $event->post->thread->project;

        LearningEvent::create([
            'student_id' => $event->user->id,
            'project_id' => $project->id,
            'event_type' => 'forum_post_unliked',
            'payload' => ['forum_post_id' => $event->post->id],
            'occurred_at' => now(),
        ]);

        RecalculateStudentProgressJob::dispatch($event->user, $project);
    }
}
