<?php

namespace Tests\Feature\Assessment;

use App\Filament\Academic\Resources\Projects\Pages\EditProject;
use App\Filament\Academic\Resources\Projects\RelationManagers\ExpectedEvidencesRelationManager;
use App\Models\User;
use App\Modules\Assessment\Models\Rubric;
use App\Modules\Assessment\Models\RubricCriterion;
use App\Modules\Assessment\Models\Submission;
use App\Modules\Project\Models\ExpectedEvidence;
use App\Modules\Project\Models\Project;
use Database\Seeders\RoleLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RubricLevelSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ExpectedEvidencesRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_register_a_submission_and_evaluate_it_with_the_rubric(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLevelSeeder::class);
        $this->seed(RubricLevelSeeder::class);

        $teacher = User::factory()->create()->assignRole('teacher');
        $student = User::factory()->create()->assignRole('student');
        $this->actingAs($teacher);
        Filament::setCurrentPanel(Filament::getPanel('academic'));

        $project = Project::factory()->create(['created_by_user_id' => $teacher->id]);
        $phase = $project->phases()->first();

        $rubric = Rubric::factory()->create();
        $criterion = RubricCriterion::factory()->for($rubric)->create();

        $evidence = ExpectedEvidence::factory()->for($phase)->create(['rubric_id' => $rubric->id]);

        $manager = Livewire::test(ExpectedEvidencesRelationManager::class, [
            'ownerRecord' => $project,
            'pageClass' => EditProject::class,
        ]);

        $manager->mountTableAction('registerSubmission', $evidence)
            ->setTableActionData(['student_id' => $student->id, 'text_content' => 'Mi entrega de texto.'])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $submission = Submission::where('expected_evidence_id', $evidence->id)
            ->where('student_id', $student->id)
            ->first();

        $this->assertNotNull($submission);
        $this->assertSame('submitted', $submission->status);

        $level = \App\Modules\Assessment\Models\RubricLevel::where('key', 'logro_esperado')->firstOrFail();

        $manager->mountTableAction('evaluateSubmissions', $evidence);

        $itemKey = array_key_first($manager->get('mountedActions.0.data.submissions'));

        $manager->set("mountedActions.0.data.submissions.{$itemKey}.results.{$criterion->id}", $level->id)
            ->set("mountedActions.0.data.submissions.{$itemKey}.feedback", 'Buen trabajo.')
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $submission->refresh();
        $this->assertSame('evaluated', $submission->status);

        $evaluation = $submission->evaluations()->where('evaluator_type', 'teacher')->first();
        $this->assertNotNull($evaluation);
        $this->assertSame('Buen trabajo.', $evaluation->feedback);
        $this->assertTrue($evaluation->consolidatedLevel()->is($level));
    }
}
