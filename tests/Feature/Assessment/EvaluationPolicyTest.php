<?php

namespace Tests\Feature\Assessment;

use App\Models\User;
use App\Modules\Assessment\Models\Evaluation;
use App\Modules\Assessment\Models\Submission;
use App\Modules\Project\Models\ExpectedEvidence;
use App\Modules\Project\Models\Project;
use Database\Seeders\RoleLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EvaluationPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLevelSeeder::class);
    }

    private function submissionForProject(Project $project): Submission
    {
        $phase = $project->phases()->first();
        $evidence = ExpectedEvidence::factory()->for($phase)->create();

        return Submission::factory()->for($evidence, 'expectedEvidence')->create();
    }

    public function test_teacher_can_create_an_evaluation_for_their_own_project(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');
        $ownProject = Project::factory()->create(['created_by_user_id' => $teacher->id]);
        $submission = $this->submissionForProject($ownProject);

        $this->assertTrue($teacher->can('create', [Evaluation::class, $submission]));
    }

    public function test_teacher_cannot_create_an_evaluation_for_someone_elses_project(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');
        $otherTeacher = User::factory()->create()->assignRole('teacher');
        $otherProject = Project::factory()->create(['created_by_user_id' => $otherTeacher->id]);
        $submission = $this->submissionForProject($otherProject);

        $this->assertFalse($teacher->can('create', [Evaluation::class, $submission]));
    }

    public function test_rector_can_create_an_evaluation_for_any_project(): void
    {
        $rector = User::factory()->create()->assignRole('rector');
        $teacher = User::factory()->create()->assignRole('teacher');
        $project = Project::factory()->create(['created_by_user_id' => $teacher->id]);
        $submission = $this->submissionForProject($project);

        $this->assertTrue($rector->can('create', [Evaluation::class, $submission]));
    }

    /**
     * Estado confirmado explícitamente con Diego durante la especificación
     * del Hito 2: submissions.evaluate no se reasignó a coordinator (se dejó
     * igual que hoy), así que coordinator no puede evaluar entregas todavía
     * — no es un descuido, quedó documentado como pendiente de decidir.
     */
    public function test_coordinator_cannot_create_an_evaluation_without_submissions_evaluate_permission(): void
    {
        $coordinator = User::factory()->create()->assignRole('coordinator');
        $project = Project::factory()->create(['created_by_user_id' => $coordinator->id]);
        $submission = $this->submissionForProject($project);

        $this->assertFalse($coordinator->hasPermissionTo('submissions.evaluate'));
        $this->assertFalse($coordinator->can('create', [Evaluation::class, $submission]));
    }
}
