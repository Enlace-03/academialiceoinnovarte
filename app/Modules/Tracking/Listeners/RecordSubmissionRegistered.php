<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Listeners;

use App\Modules\Assessment\Events\SubmissionRegistered;
use App\Modules\Tracking\Jobs\RecalculateStudentProgressJob;
use App\Modules\Tracking\Models\LearningEvent;

final class RecordSubmissionRegistered
{
    public function handle(SubmissionRegistered $event): void
    {
        $submission = $event->submission;
        $project = $submission->expectedEvidence->phase->project;

        LearningEvent::create([
            'student_id' => $submission->student_id,
            'project_id' => $project->id,
            'event_type' => 'submission_registered',
            'payload' => [
                'submission_id' => $submission->id,
                'expected_evidence_id' => $submission->expected_evidence_id,
            ],
            'occurred_at' => now(),
        ]);

        RecalculateStudentProgressJob::dispatch($submission->student, $project);
    }
}
