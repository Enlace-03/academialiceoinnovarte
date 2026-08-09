<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Listeners;

use App\Modules\Community\Events\ForumPostCreated;
use App\Modules\Tracking\Jobs\RecalculateStudentProgressJob;
use App\Modules\Tracking\Models\LearningEvent;

final class RecordForumPostCreated
{
    public function handle(ForumPostCreated $event): void
    {
        $post = $event->post;
        $project = $post->thread->project;

        LearningEvent::create([
            'student_id' => $post->user_id,
            'project_id' => $project->id,
            'event_type' => 'forum_post_created',
            'payload' => [
                'forum_post_id' => $post->id,
                'forum_thread_id' => $post->forum_thread_id,
                'is_reply' => $post->parent_post_id !== null,
            ],
            'occurred_at' => now(),
        ]);

        RecalculateStudentProgressJob::dispatch($post->user, $project);
    }
}
