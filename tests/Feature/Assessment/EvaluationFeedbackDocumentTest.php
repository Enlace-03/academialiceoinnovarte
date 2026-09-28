<?php

namespace Tests\Feature\Assessment;

use App\Filament\Academic\Resources\Projects\Pages\EditProject;
use App\Filament\Academic\Resources\Projects\RelationManagers\ExpectedEvidencesRelationManager;
use App\Models\User;
use App\Modules\Assessment\Actions\EvaluateSubmissionAction;
use App\Modules\Assessment\Models\Evaluation;
use App\Modules\Assessment\Models\EvaluationAttachment;
use App\Modules\Assessment\Models\Rubric;
use App\Modules\Assessment\Models\RubricCriterion;
use App\Modules\Assessment\Models\RubricLevel;
use App\Modules\Assessment\Models\Submission;
use App\Modules\Assessment\Models\SubmissionAttachment;
use App\Modules\Project\Models\ExpectedEvidence;
use App\Modules\Project\Models\Project;
use Database\Seeders\RoleLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RubricLevelSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Adjunto opcional de retroalimentación del docente (EvaluationAttachment):
 * ligado a la Evaluation puntual, nunca a la Submission ni mezclado con
 * SubmissionAttachment.
 */
class EvaluationFeedbackDocumentTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private Submission $submission;

    private RubricCriterion $criterion;

    private RubricLevel $level;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLevelSeeder::class);
        $this->seed(RubricLevelSeeder::class);

        Storage::fake('local');

        $this->teacher = User::factory()->create()->assignRole('teacher');
        $this->submission = Submission::factory()->create();
        $this->criterion = RubricCriterion::factory()->create();
        $this->level = RubricLevel::where('key', 'logro_esperado')->firstOrFail();
    }

    private function stored(string $name = 'devolucion.pdf'): array
    {
        $path = EvaluateSubmissionAction::FEEDBACK_DIRECTORY.'/'.$name;
        Storage::disk('local')->put($path, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");

        return ['stored_path' => $path, 'original_filename' => $name];
    }

    private function evaluate(?string $feedback, ?array $document): Evaluation
    {
        return app(EvaluateSubmissionAction::class)->execute(
            $this->teacher,
            $this->submission,
            [$this->criterion->id => $this->level->id],
            $feedback,
            feedbackDocument: $document,
        );
    }

    public function test_evaluating_with_only_a_file_and_no_text_works(): void
    {
        $evaluation = $this->evaluate(null, $this->stored());

        $this->assertNull($evaluation->feedback);
        $this->assertNotNull($evaluation->attachment);
        $this->assertSame('devolucion.pdf', $evaluation->attachment->original_filename);
        Storage::disk('local')->assertExists($evaluation->attachment->file_path);
    }

    public function test_evaluating_with_only_text_works_as_before(): void
    {
        $evaluation = $this->evaluate('Buen trabajo.', null);

        $this->assertSame('Buen trabajo.', $evaluation->feedback);
        $this->assertNull($evaluation->attachment);
        $this->assertSame(0, EvaluationAttachment::count());
    }

    public function test_evaluating_with_both_associates_both_to_that_evaluation_and_not_to_the_submission(): void
    {
        $evaluation = $this->evaluate('Buen trabajo.', $this->stored());

        $this->assertSame('Buen trabajo.', $evaluation->feedback);
        $this->assertSame($evaluation->id, EvaluationAttachment::firstOrFail()->evaluation_id);
        $this->assertSame(0, SubmissionAttachment::where('submission_id', $this->submission->id)->count());
    }

    public function test_reevaluating_without_touching_the_file_keeps_the_existing_attachment(): void
    {
        $first = $this->evaluate('Primera.', $this->stored());
        $path = $first->attachment->file_path;

        $second = $this->evaluate('Segunda.', null);

        $this->assertSame($first->id, $second->id);
        $this->assertSame('Segunda.', $second->fresh()->feedback);
        $this->assertSame(1, EvaluationAttachment::count());
        $this->assertSame($path, $second->fresh()->attachment->file_path);
        Storage::disk('local')->assertExists($path);
    }

    public function test_reevaluating_with_a_new_file_replaces_the_old_one_and_deletes_it_from_disk(): void
    {
        $first = $this->evaluate(null, $this->stored('vieja.pdf'));
        $oldPath = $first->attachment->file_path;

        $second = $this->evaluate(null, $this->stored('nueva.pdf'));

        $this->assertSame(1, EvaluationAttachment::count());
        $this->assertSame('nueva.pdf', $second->fresh()->attachment->original_filename);
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($second->fresh()->attachment->file_path);
    }

    public function test_deleting_the_attachment_removes_the_file_from_disk(): void
    {
        $evaluation = $this->evaluate(null, $this->stored());
        $path = $evaluation->attachment->file_path;

        $evaluation->attachment->delete();

        Storage::disk('local')->assertMissing($path);
    }

    public function test_a_stored_path_outside_the_feedback_directory_is_rejected(): void
    {
        Storage::disk('local')->put('submissions/de-otro-estudiante.pdf', 'x');

        $this->expectException(ValidationException::class);

        $this->evaluate(null, ['stored_path' => 'submissions/de-otro-estudiante.pdf', 'original_filename' => 'x.pdf']);
    }

    public function test_teacher_can_evaluate_from_the_filament_modal_with_only_a_file(): void
    {
        $this->actingAs($this->teacher);
        Filament::setCurrentPanel(Filament::getPanel('academic'));

        $project = Project::factory()->create(['created_by_user_id' => $this->teacher->id]);
        $rubric = Rubric::factory()->create();
        $criterion = RubricCriterion::factory()->for($rubric)->create();
        $evidence = ExpectedEvidence::factory()->for($project->phases()->first())->create(['rubric_id' => $rubric->id]);
        $submission = Submission::factory()->create(['expected_evidence_id' => $evidence->id]);

        $manager = Livewire::test(ExpectedEvidencesRelationManager::class, [
            'ownerRecord' => $project,
            'pageClass' => EditProject::class,
        ]);

        $manager->mountTableAction('evaluateSubmissions', $evidence);

        $itemKey = array_key_first($manager->get('mountedActions.0.data.submissions'));

        $manager->set("mountedActions.0.data.submissions.{$itemKey}.results.{$criterion->id}", $this->level->id)
            ->set(
                "mountedActions.0.data.submissions.{$itemKey}.feedback_document_path",
                UploadedFile::fake()->createWithContent('devolucion.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF"),
            )
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $evaluation = $submission->evaluations()->where('evaluator_type', 'teacher')->firstOrFail();

        $this->assertNull($evaluation->feedback);
        $this->assertNotNull($evaluation->attachment);
        $this->assertStringStartsWith('evaluation-feedback/', $evaluation->attachment->file_path);
        Storage::disk('local')->assertExists($evaluation->attachment->file_path);
    }
}
