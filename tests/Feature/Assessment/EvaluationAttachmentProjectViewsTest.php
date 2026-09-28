<?php

namespace Tests\Feature\Assessment;

use App\Livewire\Parent\ChildProjectShow;
use App\Livewire\Student\ProjectShow;
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
 * El documento de retroalimentación del docente aparece también en las
 * vistas de PROYECTO (estudiante y acudiente), junto al comentario de texto,
 * y solo para quien es dueño de esa entrega (o su acudiente): un estudiante
 * ajeno del mismo ciclo ve su propio estado, no el de otro.
 */
class EvaluationAttachmentProjectViewsTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private User $otherStudent;

    private Project $project;

    private EvaluationAttachment $attachment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLevelSeeder::class);
        $this->seed(RubricLevelSeeder::class);

        Storage::fake('local');

        $cycle = Cycle::factory()->create();
        $grade = SchoolGrade::factory()->create(['cycle_id' => $cycle->id]);

        $this->student = User::factory()->create(['school_grade_id' => $grade->id])->assignRole('student');
        $this->otherStudent = User::factory()->create(['school_grade_id' => $grade->id])->assignRole('student');

        $this->project = Project::factory()->create(['cycle_id' => $cycle->id]);
        $evidence = ExpectedEvidence::factory()->create(['phase_id' => $this->project->phases()->first()->id]);

        $submission = Submission::factory()->create([
            'expected_evidence_id' => $evidence->id,
            'student_id' => $this->student->id,
            'status' => 'evaluated',
        ]);

        $evaluation = Evaluation::factory()->create(['submission_id' => $submission->id, 'feedback' => 'Muy bien.']);

        EvaluationResult::create([
            'evaluation_id' => $evaluation->id,
            'rubric_criterion_id' => RubricCriterion::factory()->create()->id,
            'rubric_level_id' => RubricLevel::where('key', 'logro_esperado')->firstOrFail()->id,
        ]);

        $this->attachment = EvaluationAttachment::factory()->create([
            'evaluation_id' => $evaluation->id,
            'original_filename' => 'devolucion-proyecto.pdf',
        ]);
    }

    public function test_the_owning_student_sees_the_document_in_the_project_view(): void
    {
        $this->actingAs($this->student);

        Livewire::test(ProjectShow::class, ['project' => $this->project])
            ->assertSee('Muy bien.')
            ->assertSee('Documento del docente')
            ->assertSee('devolucion-proyecto.pdf')
            ->assertSee(route('evaluations.attachments.show', $this->attachment));
    }

    public function test_the_guardian_sees_the_document_in_the_child_project_view(): void
    {
        $guardian = User::factory()->create()->assignRole('parent');
        $guardian->children()->attach($this->student->id, ['relationship' => 'madre']);
        $this->actingAs($guardian);

        Livewire::test(ChildProjectShow::class, ['child' => $this->student, 'project' => $this->project])
            ->assertSee('Muy bien.')
            ->assertSee('Documento del docente')
            ->assertSee('devolucion-proyecto.pdf')
            ->assertSee(route('evaluations.attachments.show', $this->attachment));
    }

    public function test_a_student_outside_that_submission_does_not_see_the_document(): void
    {
        $this->actingAs($this->otherStudent);

        Livewire::test(ProjectShow::class, ['project' => $this->project])
            ->assertDontSee('Documento del docente')
            ->assertDontSee('devolucion-proyecto.pdf')
            ->assertDontSee(route('evaluations.attachments.show', $this->attachment));
    }

    public function test_a_guardian_of_another_student_cannot_open_the_child_project_view_and_never_sees_it(): void
    {
        $guardian = User::factory()->create()->assignRole('parent');
        $guardian->children()->attach($this->otherStudent->id, ['relationship' => 'madre']);
        $this->actingAs($guardian);

        Livewire::test(ChildProjectShow::class, ['child' => $this->student, 'project' => $this->project])
            ->assertForbidden();
    }
}
