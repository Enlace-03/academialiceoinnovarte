<?php

namespace Tests\Feature\Assessment;

use App\Models\User;
use App\Modules\Assessment\Models\Submission;
use App\Modules\Project\Models\ExpectedEvidence;
use App\Modules\Project\Models\Project;
use Database\Seeders\RoleLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SubmissionPolicy no existía antes del Hito 3b-1 -- el personal nunca la
 * necesitó (autoriza directo con ProjectPolicy::update en el RelationManager
 * de Filament). Es la puerta que le falta al estudiante para ver su propia
 * entrega desde fuera de Filament.
 */
class SubmissionPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLevelSeeder::class);
    }

    private function submissionFor(Project $project, User $student): Submission
    {
        $phase = $project->phases()->first();
        $evidence = ExpectedEvidence::factory()->create(['phase_id' => $phase->id]);

        return Submission::factory()->create([
            'expected_evidence_id' => $evidence->id,
            'student_id' => $student->id,
        ]);
    }

    public function test_student_can_view_own_submission(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');
        $student = User::factory()->create()->assignRole('student');
        $project = Project::factory()->create(['created_by_user_id' => $teacher->id]);
        $submission = $this->submissionFor($project, $student);

        $this->assertTrue($student->can('view', $submission));
    }

    public function test_student_cannot_view_someone_elses_submission(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');
        $student = User::factory()->create()->assignRole('student');
        $otherStudent = User::factory()->create()->assignRole('student');
        $project = Project::factory()->create(['created_by_user_id' => $teacher->id]);
        $submission = $this->submissionFor($project, $student);

        $this->assertFalse($otherStudent->can('view', $submission));
    }

    public function test_teacher_can_view_a_submission_in_their_own_project(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');
        $student = User::factory()->create()->assignRole('student');
        $project = Project::factory()->create(['created_by_user_id' => $teacher->id]);
        $submission = $this->submissionFor($project, $student);

        $this->assertTrue($teacher->can('view', $submission));
    }

    public function test_teacher_cannot_view_a_submission_in_another_teachers_project(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');
        $otherTeacher = User::factory()->create()->assignRole('teacher');
        $student = User::factory()->create()->assignRole('student');
        $project = Project::factory()->create(['created_by_user_id' => $otherTeacher->id]);
        $submission = $this->submissionFor($project, $student);

        $this->assertFalse($teacher->can('view', $submission));
    }

    public function test_rector_can_view_any_submission(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');
        $rector = User::factory()->create()->assignRole('rector');
        $student = User::factory()->create()->assignRole('student');
        $project = Project::factory()->create(['created_by_user_id' => $teacher->id]);
        $submission = $this->submissionFor($project, $student);

        $this->assertTrue($rector->can('view', $submission));
    }
}
