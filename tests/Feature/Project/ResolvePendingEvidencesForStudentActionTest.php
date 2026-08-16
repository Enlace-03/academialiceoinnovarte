<?php

namespace Tests\Feature\Project;

use App\Models\User;
use App\Modules\Assessment\Models\Submission;
use App\Modules\Project\Actions\ResolvePendingEvidencesForStudentAction;
use App\Modules\Project\Models\ExpectedEvidence;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\StudentPhaseSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ResolvePendingEvidencesForStudentActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_evidence_with_a_future_end_date(): void
    {
        $student = User::factory()->create();
        $project = Project::factory()->create();
        $phase = $project->phases()->first();
        $evidence = ExpectedEvidence::factory()->create(['phase_id' => $phase->id, 'description' => 'Ensayo final']);
        StudentPhaseSchedule::factory()->create([
            'student_id' => $student->id,
            'phase_id' => $phase->id,
            'end_date' => now()->addDays(5)->toDateString(),
        ]);

        $result = app(ResolvePendingEvidencesForStudentAction::class)->execute($student);

        $this->assertCount(1, $result);
        $this->assertTrue($result->first()['evidence']->is($evidence));
        $this->assertTrue($result->first()['project']->is($project));
    }

    public function test_excludes_evidence_already_submitted_or_evaluated(): void
    {
        $student = User::factory()->create();
        $project = Project::factory()->create();
        $phase = $project->phases()->first();
        $evidence = ExpectedEvidence::factory()->create(['phase_id' => $phase->id]);
        StudentPhaseSchedule::factory()->create([
            'student_id' => $student->id,
            'phase_id' => $phase->id,
            'end_date' => now()->addDays(5)->toDateString(),
        ]);
        Submission::factory()->create([
            'expected_evidence_id' => $evidence->id,
            'student_id' => $student->id,
            'status' => 'submitted',
        ]);

        $result = app(ResolvePendingEvidencesForStudentAction::class)->execute($student);

        $this->assertCount(0, $result);
    }

    public function test_excludes_schedules_of_another_student(): void
    {
        $student = User::factory()->create();
        $otherStudent = User::factory()->create();
        $project = Project::factory()->create();
        $phase = $project->phases()->first();
        ExpectedEvidence::factory()->create(['phase_id' => $phase->id]);
        StudentPhaseSchedule::factory()->create([
            'student_id' => $otherStudent->id,
            'phase_id' => $phase->id,
            'end_date' => now()->addDays(5)->toDateString(),
        ]);

        $result = app(ResolvePendingEvidencesForStudentAction::class)->execute($student);

        $this->assertCount(0, $result);
    }

    public function test_from_excludes_schedules_before_the_given_date(): void
    {
        $student = User::factory()->create();
        $project = Project::factory()->create();
        $phase = $project->phases()->first();
        ExpectedEvidence::factory()->create(['phase_id' => $phase->id]);
        StudentPhaseSchedule::factory()->create([
            'student_id' => $student->id,
            'phase_id' => $phase->id,
            'end_date' => '2026-03-10',
        ]);

        $result = app(ResolvePendingEvidencesForStudentAction::class)
            ->execute($student, Carbon::parse('2026-03-15'));

        $this->assertCount(0, $result);
    }

    public function test_from_includes_a_schedule_already_overdue_on_the_given_date(): void
    {
        $student = User::factory()->create();
        $project = Project::factory()->create();
        $phase = $project->phases()->first();
        ExpectedEvidence::factory()->create(['phase_id' => $phase->id]);
        StudentPhaseSchedule::factory()->create([
            'student_id' => $student->id,
            'phase_id' => $phase->id,
            'end_date' => '2026-03-10',
        ]);

        $result = app(ResolvePendingEvidencesForStudentAction::class)
            ->execute($student, Carbon::parse('2026-03-01'), Carbon::parse('2026-03-31'));

        $this->assertCount(1, $result);
    }

    public function test_to_excludes_schedules_after_the_given_date(): void
    {
        $student = User::factory()->create();
        $project = Project::factory()->create();
        $phase = $project->phases()->first();
        ExpectedEvidence::factory()->create(['phase_id' => $phase->id]);
        StudentPhaseSchedule::factory()->create([
            'student_id' => $student->id,
            'phase_id' => $phase->id,
            'end_date' => now()->addDays(10)->toDateString(),
        ]);

        $result = app(ResolvePendingEvidencesForStudentAction::class)
            ->execute($student, now(), now()->addDays(7));

        $this->assertCount(0, $result);
    }
}
