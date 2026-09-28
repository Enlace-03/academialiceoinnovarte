<?php

namespace Tests\Feature\Assessment;

use App\Livewire\Student\EvidenceShow;
use App\Models\User;
use App\Modules\Assessment\Models\Evaluation;
use App\Modules\Assessment\Models\EvaluationAttachment;
use App\Modules\Assessment\Models\EvaluationResult;
use App\Modules\Assessment\Models\RubricCriterion;
use App\Modules\Assessment\Models\RubricLevel;
use App\Modules\Assessment\Models\Submission;
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
use Tests\TestCase;

/**
 * Ruta evaluations.attachments.show + EvaluationAttachmentPolicy::view().
 * Ningún usuario de estos tests es super_admin (Gate::before) para que la
 * autorización real sea la que se ejercita.
 */
class EvaluationAttachmentAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private User $evaluator;

    private User $projectOwner;

    private Project $project;

    private Submission $submission;

    private EvaluationAttachment $attachment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLevelSeeder::class);

        Storage::fake('local');

        $cycle = Cycle::factory()->create();
        $grade = SchoolGrade::factory()->create(['cycle_id' => $cycle->id]);

        $this->student = User::factory()->create(['school_grade_id' => $grade->id])->assignRole('student');
        $this->evaluator = User::factory()->create()->assignRole('teacher');
        $this->projectOwner = User::factory()->create()->assignRole('teacher');

        $this->project = Project::factory()->create(['cycle_id' => $cycle->id, 'created_by_user_id' => $this->projectOwner->id]);
        $evidence = ExpectedEvidence::factory()->create(['phase_id' => $this->project->phases()->first()->id]);

        $this->submission = Submission::factory()->create([
            'expected_evidence_id' => $evidence->id,
            'student_id' => $this->student->id,
            'status' => 'evaluated',
        ]);

        $evaluation = Evaluation::factory()->create([
            'submission_id' => $this->submission->id,
            'evaluated_by' => $this->evaluator->id,
        ]);

        // La vista del estudiante exige un nivel consolidado para 'evaluada'.
        $this->seed(RubricLevelSeeder::class);
        EvaluationResult::create([
            'evaluation_id' => $evaluation->id,
            'rubric_criterion_id' => RubricCriterion::factory()->create()->id,
            'rubric_level_id' => RubricLevel::where('key', 'logro_esperado')->firstOrFail()->id,
        ]);

        $this->attachment = EvaluationAttachment::factory()->create([
            'evaluation_id' => $evaluation->id,
            'original_filename' => 'devolucion.pdf',
        ]);
    }

    private function url(): string
    {
        return route('evaluations.attachments.show', $this->attachment);
    }

    public function test_the_owning_student_can_download_it_as_an_attachment(): void
    {
        $response = $this->actingAs($this->student)->get($this->url());

        $response->assertOk();
        $this->assertStringContainsString('attachment', $response->headers->get('content-disposition'));
        $this->assertStringContainsString('devolucion.pdf', $response->headers->get('content-disposition'));
        $response->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox");
    }

    public function test_the_guardian_of_the_student_can_download_it(): void
    {
        $guardian = User::factory()->create()->assignRole('parent');
        $guardian->children()->attach($this->student->id, ['relationship' => 'madre']);

        $this->actingAs($guardian)->get($this->url())->assertOk();
    }

    public function test_a_guardian_of_another_student_cannot_download_it(): void
    {
        $otherStudent = User::factory()->create()->assignRole('student');
        $guardian = User::factory()->create()->assignRole('parent');
        $guardian->children()->attach($otherStudent->id, ['relationship' => 'madre']);

        $this->actingAs($guardian)->get($this->url())->assertForbidden();
    }

    public function test_the_teacher_who_evaluated_can_download_it(): void
    {
        $this->actingAs($this->evaluator)->get($this->url())->assertOk();
    }

    public function test_a_different_teacher_with_project_access_cannot_download_it(): void
    {
        // Sí puede ver la entrega (SubmissionPolicy / viewAsStaff)...
        $this->assertTrue($this->projectOwner->can('view', $this->submission));

        // ...pero no el documento de retroalimentación de otro docente.
        $this->actingAs($this->projectOwner)->get($this->url())->assertForbidden();
    }

    public function test_rector_and_coordinator_can_download_it(): void
    {
        foreach (['rector', 'coordinator'] as $role) {
            $user = User::factory()->create()->assignRole($role);

            $this->actingAs($user)->get($this->url())->assertOk();
        }
    }

    public function test_a_student_from_another_submission_cannot_download_it(): void
    {
        $other = User::factory()->create()->assignRole('student');

        $this->actingAs($other)->get($this->url())->assertForbidden();
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get($this->url())->assertRedirect();
    }

    public function test_a_path_traversal_original_filename_is_sanitized(): void
    {
        $this->attachment->update(['original_filename' => '../../.env']);

        $response = $this->actingAs($this->student)->get($this->url());

        $response->assertOk();
        $this->assertStringNotContainsString('/', $response->headers->get('content-disposition'));
        $this->assertStringNotContainsString('..', $response->headers->get('content-disposition'));
    }

    public function test_the_student_sees_the_teacher_document_in_its_own_section_and_not_in_their_attachments(): void
    {
        $this->actingAs($this->student);

        Livewire::test(EvidenceShow::class, ['project' => $this->project, 'evidence' => $this->submission->expectedEvidence])
            ->assertSee('Documento del docente')
            ->assertSee('devolucion.pdf')
            ->assertSee($this->url());

        $this->assertSame(0, $this->submission->attachments()->count());
    }
}
