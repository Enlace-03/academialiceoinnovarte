<?php

namespace Tests\Feature\Assessment;

use App\Models\User;
use App\Modules\Assessment\Models\Evaluation;
use App\Modules\Assessment\Models\EvaluationResult;
use App\Modules\Assessment\Models\Observation;
use App\Modules\Assessment\Models\Rubric;
use App\Modules\Assessment\Models\RubricCriterion;
use App\Modules\Assessment\Models\RubricLevel;
use App\Modules\Assessment\Models\Submission;
use App\Modules\Project\Models\ExpectedEvidence;
use App\Modules\Project\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentModelRelationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_rubric_relations(): void
    {
        $rubric = Rubric::factory()->create();
        $criterion = RubricCriterion::factory()->for($rubric)->create();

        $this->assertTrue($rubric->criteria->contains($criterion));
    }

    public function test_expected_evidence_rubric_and_submissions_relations(): void
    {
        $rubric = Rubric::factory()->create();
        $evidence = ExpectedEvidence::factory()->create(['rubric_id' => $rubric->id]);
        $submission = Submission::factory()->for($evidence, 'expectedEvidence')->create();

        $this->assertTrue($evidence->rubric->is($rubric));
        $this->assertTrue($evidence->submissions->contains($submission));
    }

    public function test_submission_relations(): void
    {
        $student = User::factory()->create();
        $submission = Submission::factory()->create(['student_id' => $student->id]);
        $evaluation = Evaluation::factory()->for($submission)->create();

        $this->assertTrue($submission->student->is($student));
        $this->assertInstanceOf(ExpectedEvidence::class, $submission->expectedEvidence);
        $this->assertTrue($submission->evaluations->contains($evaluation));
    }

    public function test_evaluation_relations(): void
    {
        $teacher = User::factory()->create();
        $evaluation = Evaluation::factory()->create(['evaluated_by' => $teacher->id]);
        $result = EvaluationResult::factory()->for($evaluation)->create();

        $this->assertTrue($evaluation->evaluatedBy->is($teacher));
        $this->assertInstanceOf(Submission::class, $evaluation->submission);
        $this->assertTrue($evaluation->results->contains($result));
    }

    public function test_evaluation_result_relations(): void
    {
        $criterion = RubricCriterion::factory()->create();
        $level = RubricLevel::factory()->create();
        $result = EvaluationResult::factory()->create([
            'rubric_criterion_id' => $criterion->id,
            'rubric_level_id' => $level->id,
        ]);

        $this->assertTrue($result->rubricCriterion->is($criterion));
        $this->assertTrue($result->rubricLevel->is($level));
        $this->assertInstanceOf(Evaluation::class, $result->evaluation);
    }

    public function test_observation_relations(): void
    {
        $student = User::factory()->create();
        $teacher = User::factory()->create();
        $project = Project::factory()->create();

        $observation = Observation::factory()->create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'project_id' => $project->id,
        ]);

        $this->assertTrue($observation->student->is($student));
        $this->assertTrue($observation->teacher->is($teacher));
        $this->assertTrue($observation->project->is($project));
    }

    public function test_observation_project_is_optional(): void
    {
        $observation = Observation::factory()->create(['project_id' => null]);

        $this->assertNull($observation->project);
    }
}
