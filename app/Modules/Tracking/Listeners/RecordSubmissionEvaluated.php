<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Listeners;

use App\Modules\Assessment\Events\SubmissionEvaluated;
use App\Modules\Tracking\Jobs\RecalculateStudentProgressJob;
use App\Modules\Tracking\Models\LearningEvent;

final class RecordSubmissionEvaluated
{
    public function handle(SubmissionEvaluated $event): void
    {
        $submission = $event->evaluation->submission;
        $project = $submission->expectedEvidence->phase->project;

        LearningEvent::create([
            'student_id' => $submission->student_id,
            'project_id' => $project->id,
            'event_type' => 'submission_evaluated',
            'payload' => [
                'submission_id' => $submission->id,
                'evaluation_id' => $event->evaluation->id,
            ],
            'occurred_at' => now(),
        ]);

        RecalculateStudentProgressJob::dispatch($submission->student, $project);
    }
}
