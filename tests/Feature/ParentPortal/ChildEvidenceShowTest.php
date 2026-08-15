<?php

namespace Tests\Feature\ParentPortal;

use App\Livewire\Parent\ChildEvidenceShow;
use App\Models\User;
use App\Modules\Assessment\Models\Evaluation;
use App\Modules\Assessment\Models\EvaluationResult;
use App\Modules\Assessment\Models\Rubric;
use App\Modules\Assessment\Models\RubricCriterion;
use App\Modules\Assessment\Models\RubricLevel;
use App\Modules\Assessment\Models\Submission;
use App\Modules\Assessment\Models\SubmissionAttachment;
use App\Modules\Institution\Models\Cycle;
use App\Modules\Institution\Models\SchoolGrade;
use App\Modules\Project\Models\ExpectedEvidence;
use App\Modules\Project\Models\Project;
use Database\Seeders\RoleLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RubricLevelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Cuarto y último nivel del drill-down: mismo contenido de solo lectura
 * que Student\EvidenceShow (instrucciones, rúbrica con resaltado por
 * criterio, adjuntos ya entregados, retroalimentación) para $child -- SIN
 * formulario de entrega. test_component_has_no_writable_public_methods()
 * confirma por reflection, no solo por ausencia de botones en el Blade,
 * que no existe ningún método público de escritura (a diferencia de
 * Student\EvidenceShow, que tiene seis).
 */
class ChildEvidenceShowTest extends TestCase
{
    use RefreshDatabase;

    private User $guardian;

    private User $child;

    private Project $project;

    private ExpectedEvidence $evidence;

    private RubricCriterion $criterionOne;

    private RubricCriterion $criterionTwo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLevelSeeder::class);
        $this->seed(RubricLevelSeeder::class);
        Storage::fake('local');

        $this->guardian = User::factory()->create()->assignRole('parent');
        $cycle = Cycle::factory()->create();
        $grade = SchoolGrade::factory()->create(['cycle_id' => $cycle->id]);
        $this->child = User::factory()->create(['school_grade_id' => $grade->id])->assignRole('student');
        $this->guardian->children()->attach($this->child->id, ['relationship' => 'madre']);

        $this->project = Project::factory()->create(['cycle_id' => $cycle->id]);
        $phase = $this->project->phases()->first();
        $rubric = Rubric::factory()->create();
        $this->criterionOne = RubricCriterion::factory()->create(['rubric_id' => $rubric->id, 'position' => 1]);
        $this->criterionTwo = RubricCriterion::factory()->create(['rubric_id' => $rubric->id, 'position' => 2]);
        $this->evidence = ExpectedEvidence::factory()->create(['phase_id' => $phase->id, 'rubric_id' => $rubric->id]);
    }

    public function test_guardian_sees_the_childs_submitted_attachments_and_feedback(): void
    {
        $submission = Submission::factory()->create([
            'expected_evidence_id' => $this->evidence->id,
            'student_id' => $this->child->id,
            'text_content' => 'Aquí está mi entrega.',
        ]);
        SubmissionAttachment::factory()->photo()->create(['submission_id' => $submission->id, 'original_filename' => 'foto-entrega.jpg']);
        $evaluation = Evaluation::factory()->create([
            'submission_id' => $submission->id,
            'evaluator_type' => 'teacher',
            'feedback' => 'Buen trabajo, felicitaciones.',
        ]);
        EvaluationResult::factory()->create([
            'evaluation_id' => $evaluation->id,
            'rubric_criterion_id' => $this->criterionOne->id,
            'rubric_level_id' => RubricLevel::where('key', 'logro_esperado')->firstOrFail()->id,
        ]);

        $response = $this->actingAs($this->guardian)->get(route('parent.child.evidence.show', [
            'child' => $this->child, 'project' => $this->project, 'evidence' => $this->evidence,
        ]));

        $response->assertOk();
        $response->assertSee('Aquí está mi entrega.');
        $response->assertSee('foto-entrega.jpg');
        $response->assertSee('Buen trabajo, felicitaciones.');
        $response->assertSee('Logro esperado');
    }

    public function test_partial_evaluation_only_highlights_the_evaluated_criterion(): void
    {
        $submission = Submission::factory()->create([
            'expected_evidence_id' => $this->evidence->id,
            'student_id' => $this->child->id,
        ]);
        $evaluation = Evaluation::factory()->create([
            'submission_id' => $submission->id,
            'evaluator_type' => 'teacher',
        ]);
        $level = RubricLevel::where('key', 'logro_esperado')->firstOrFail();
        EvaluationResult::factory()->create([
            'evaluation_id' => $evaluation->id,
            'rubric_criterion_id' => $this->criterionOne->id,
            'rubric_level_id' => $level->id,
        ]);

        $this->actingAs($this->guardian);
        $component = Livewire::test(ChildEvidenceShow::class, [
            'child' => $this->child, 'project' => $this->project, 'evidence' => $this->evidence,
        ]);

        $results = $component->instance()->resultsByCriterion();

        $this->assertCount(1, $results);
        $this->assertArrayHasKey($this->criterionOne->id, $results);
        $this->assertArrayNotHasKey($this->criterionTwo->id, $results);
        $component->assertSee('Sin evaluar');
    }

    public function test_guardian_sees_pending_when_the_child_has_not_submitted_yet(): void
    {
        $this->actingAs($this->guardian);
        $component = Livewire::test(ChildEvidenceShow::class, [
            'child' => $this->child, 'project' => $this->project, 'evidence' => $this->evidence,
        ]);

        $this->assertSame(['status' => 'pendiente'], $component->instance()->evidenceState());
    }

    public function test_evidence_not_belonging_to_the_project_returns_404(): void
    {
        $otherProject = Project::factory()->create();
        $otherPhase = $otherProject->phases()->first();
        $unrelatedEvidence = ExpectedEvidence::factory()->create(['phase_id' => $otherPhase->id]);

        $this->actingAs($this->guardian)
            ->get(route('parent.child.evidence.show', [
                'child' => $this->child, 'project' => $this->project, 'evidence' => $unrelatedEvidence,
            ]))
            ->assertNotFound();
    }

    public function test_guardian_cannot_access_a_child_that_is_not_theirs_via_direct_url(): void
    {
        $otherFamilysChild = User::factory()->create(['school_grade_id' => $this->child->school_grade_id])->assignRole('student');

        $this->actingAs($this->guardian)
            ->get(route('parent.child.evidence.show', [
                'child' => $otherFamilysChild, 'project' => $this->project, 'evidence' => $this->evidence,
            ]))
            ->assertForbidden();
    }

    public function test_guardian_cannot_access_evidence_of_a_draft_project(): void
    {
        $draft = Project::factory()->draft()->create(['cycle_id' => $this->child->schoolGrade->cycle_id]);
        $phase = $draft->phases()->first();
        $evidence = ExpectedEvidence::factory()->create(['phase_id' => $phase->id]);

        $this->actingAs($this->guardian)
            ->get(route('parent.child.evidence.show', [
                'child' => $this->child, 'project' => $draft, 'evidence' => $evidence,
            ]))
            ->assertForbidden();
    }

    /**
     * Confirmación explícita, por reflection -- no solo por ausencia de
     * botones/formulario en el Blade -- de que ningún método público
     * mutable existe. Student\EvidenceShow tiene submit(),
     * startResubmission(), addLink(), removeNewPhoto(), removeNewLink() y
     * removeExisting(); ninguno de esos seis existe acá.
     */
    public function test_component_has_no_writable_public_methods(): void
    {
        $publicMethods = collect((new ReflectionClass(ChildEvidenceShow::class))->getMethods(ReflectionMethod::IS_PUBLIC))
            ->filter(fn (ReflectionMethod $method): bool => $method->getDeclaringClass()->getName() === ChildEvidenceShow::class)
            ->map(fn (ReflectionMethod $method): string => $method->getName())
            ->values()
            ->all();

        $this->assertEqualsCanonicalizing(
            ['mount', 'submission', 'resultsByCriterion', 'evidenceState', 'render'],
            $publicMethods
        );
    }

    public function test_no_submission_form_is_rendered(): void
    {
        $response = $this->actingAs($this->guardian)->get(route('parent.child.evidence.show', [
            'child' => $this->child, 'project' => $this->project, 'evidence' => $this->evidence,
        ]));

        $response->assertOk();
        $response->assertDontSee('wire:click="submit"', false);
        $response->assertDontSee('wire:click="startResubmission"', false);
        $response->assertDontSee('<textarea', false);
        $response->assertDontSee('type="file"', false);
    }
}
