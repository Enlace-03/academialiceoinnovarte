<?php

namespace Tests\Feature\Assessment;

use App\Models\User;
use App\Modules\Assessment\Actions\EvaluateSubmissionAction;
use App\Modules\Assessment\Models\RubricCriterion;
use App\Modules\Assessment\Models\RubricLevel;
use App\Modules\Assessment\Models\Submission;
use Database\Seeders\RubricLevelSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EvaluateSubmissionActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_unique_submission_and_evaluator_type_allows_two_different_types(): void
    {
        $this->seed(RubricLevelSeeder::class);

        $submission = Submission::factory()->create();
        $teacher = User::factory()->create();
        $student = User::factory()->create();
        $criterion = RubricCriterion::factory()->create();
        $level = RubricLevel::where('key', 'inicio')->firstOrFail();

        $action = app(EvaluateSubmissionAction::class);

        $action->execute($teacher, $submission, [$criterion->id => $level->id], null, 'teacher');
        $action->execute($student, $submission, [$criterion->id => $level->id], null, 'self');

        $this->assertSame(2, $submission->evaluations()->count());
        $this->assertDatabaseHas('evaluations', ['submission_id' => $submission->id, 'evaluator_type' => 'teacher']);
        $this->assertDatabaseHas('evaluations', ['submission_id' => $submission->id, 'evaluator_type' => 'self']);
    }

    public function test_same_evaluator_type_twice_updates_instead_of_duplicating(): void
    {
        $this->seed(RubricLevelSeeder::class);

        $submission = Submission::factory()->create();
        $teacher = User::factory()->create();
        $criterion = RubricCriterion::factory()->create();
        $levelLow = RubricLevel::where('key', 'inicio')->firstOrFail();
        $levelHigh = RubricLevel::where('key', 'logro_destacado')->firstOrFail();

        $action = app(EvaluateSubmissionAction::class);
        $action->execute($teacher, $submission, [$criterion->id => $levelLow->id], null, 'teacher');
        $action->execute($teacher, $submission, [$criterion->id => $levelHigh->id], 'mejoró', 'teacher');

        $this->assertSame(1, $submission->evaluations()->count());
        $this->assertDatabaseHas('evaluations', ['submission_id' => $submission->id, 'evaluator_type' => 'teacher', 'feedback' => 'mejoró']);
    }

    public function test_database_constraint_rejects_duplicate_submission_and_evaluator_type_via_raw_insert(): void
    {
        $this->seed(RubricLevelSeeder::class);

        $submission = Submission::factory()->create();
        $teacher = User::factory()->create();

        \App\Modules\Assessment\Models\Evaluation::create([
            'submission_id' => $submission->id,
            'evaluated_by' => $teacher->id,
            'evaluator_type' => 'teacher',
            'evaluated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        \App\Modules\Assessment\Models\Evaluation::create([
            'submission_id' => $submission->id,
            'evaluated_by' => $teacher->id,
            'evaluator_type' => 'teacher',
            'evaluated_at' => now(),
        ]);
    }

    public function test_evaluating_a_submission_marks_it_as_evaluated(): void
    {
        $this->seed(RubricLevelSeeder::class);

        $submission = Submission::factory()->create(['status' => 'submitted']);
        $teacher = User::factory()->create();
        $criterion = RubricCriterion::factory()->create();
        $level = RubricLevel::first();

        app(EvaluateSubmissionAction::class)->execute($teacher, $submission, [$criterion->id => $level->id]);

        $this->assertSame('evaluated', $submission->fresh()->status);
    }
}
