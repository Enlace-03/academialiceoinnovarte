<?php

namespace Tests\Feature\Student;

use App\Livewire\Student\ProjectShow;
use App\Models\User;
use App\Modules\Assessment\Models\Evaluation;
use App\Modules\Assessment\Models\EvaluationResult;
use App\Modules\Assessment\Models\RubricLevel;
use App\Modules\Assessment\Models\Submission;
use App\Modules\Institution\Models\Cycle;
use App\Modules\Institution\Models\SchoolGrade;
use App\Modules\Project\Models\ExpectedEvidence;
use App\Modules\Project\Models\Guide;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\Resource;
use App\Modules\Tracking\Models\StudentProgress;
use Database\Seeders\RoleLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RubricLevelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProjectShowTest extends TestCase
{
    use RefreshDatabase;

    private Cycle $ownCycle;

    private User $student;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLevelSeeder::class);

        $this->ownCycle = Cycle::factory()->create();
        $grade = SchoolGrade::factory()->create(['cycle_id' => $this->ownCycle->id]);
        $this->student = User::factory()->create(['school_grade_id' => $grade->id])->assignRole('student');
        $this->project = Project::factory()->create(['cycle_id' => $this->ownCycle->id, 'title' => 'Huerta escolar']);
    }

    public function test_student_in_own_cycle_can_view_the_project(): void
    {
        $response = $this->actingAs($this->student)->get(route('student.projects.show', $this->project->uuid));

        $response->assertOk();
        $response->assertSee('Huerta escolar');
    }

    public function test_student_in_other_cycle_is_forbidden_even_via_direct_url(): void
    {
        $otherCycle = Cycle::factory()->create();
        $otherGrade = SchoolGrade::factory()->create(['cycle_id' => $otherCycle->id]);
        $otherStudent = User::factory()->create(['school_grade_id' => $otherGrade->id])->assignRole('student');

        $this->actingAs($otherStudent)
            ->get(route('student.projects.show', $this->project->uuid))
            ->assertForbidden();
    }

    /**
     * Defensa en profundidad: el middleware role:student bloquea ANTES de
     * que se evalúe ninguna Policy -- un teacher/parent recibe 403 aquí
     * incluso si nunca se llega a instanciar ProjectShow ni a resolver el
     * {project} de la URL.
     */
    public function test_teacher_cannot_access_project_show_route_middleware(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');

        $this->actingAs($teacher)
            ->get(route('student.projects.show', $this->project->uuid))
            ->assertForbidden();
    }

    public function test_parent_cannot_access_project_show_route_middleware(): void
    {
        $parent = User::factory()->create()->assignRole('parent');

        $this->actingAs($parent)
            ->get(route('student.projects.show', $this->project->uuid))
            ->assertForbidden();
    }

    public function test_shows_phases_guides_and_resources(): void
    {
        $phase = $this->project->phases()->first();
        Guide::factory()->create(['phase_id' => $phase->id, 'title' => 'Guía de siembra']);
        Resource::factory()->create(['phase_id' => $phase->id, 'guide_id' => null, 'title' => 'Video introductorio']);

        $response = $this->actingAs($this->student)->get(route('student.projects.show', $this->project->uuid));

        $response->assertOk();
        $response->assertSee($phase->name);
        $response->assertSee('Guía de siembra');
        $response->assertSee('Video introductorio');
    }

    public function test_evidence_status_is_pending_without_a_submission(): void
    {
        $phase = $this->project->phases()->first();
        $evidence = ExpectedEvidence::factory()->create(['phase_id' => $phase->id]);

        $this->actingAs($this->student);
        $component = Livewire::test(ProjectShow::class, ['project' => $this->project]);

        $status = $component->instance()->evidenceStatus($evidence);

        $this->assertSame('pendiente', $status['status']);
    }

    public function test_evidence_status_is_entregada_with_an_unevaluated_submission(): void
    {
        $phase = $this->project->phases()->first();
        $evidence = ExpectedEvidence::factory()->create(['phase_id' => $phase->id]);
        Submission::factory()->create([
            'expected_evidence_id' => $evidence->id,
            'student_id' => $this->student->id,
        ]);

        $this->actingAs($this->student);
        $component = Livewire::test(ProjectShow::class, ['project' => $this->project]);

        $status = $component->instance()->evidenceStatus($evidence);

        $this->assertSame('entregada', $status['status']);
    }

    public function test_evidence_status_is_evaluada_with_the_qualitative_level_and_feedback_never_a_number(): void
    {
        $this->seed(RubricLevelSeeder::class);

        $phase = $this->project->phases()->first();
        $evidence = ExpectedEvidence::factory()->create(['phase_id' => $phase->id]);
        $submission = Submission::factory()->create([
            'expected_evidence_id' => $evidence->id,
            'student_id' => $this->student->id,
        ]);
        $evaluation = Evaluation::factory()->create([
            'submission_id' => $submission->id,
            'evaluator_type' => 'teacher',
            'feedback' => 'Excelente trabajo en equipo.',
        ]);
        $level = RubricLevel::where('key', 'logro_destacado')->firstOrFail();
        EvaluationResult::factory()->create([
            'evaluation_id' => $evaluation->id,
            'rubric_level_id' => $level->id,
        ]);

        $this->actingAs($this->student);
        $component = Livewire::test(ProjectShow::class, ['project' => $this->project]);

        $status = $component->instance()->evidenceStatus($evidence);

        $this->assertSame('evaluada', $status['status']);
        $this->assertTrue($status['level']->is($level));
        $this->assertSame('Excelente trabajo en equipo.', $status['feedback']);

        $response = $this->actingAs($this->student)->get(route('student.projects.show', $this->project->uuid));
        $response->assertSee('Logro destacado');
    }

    public function test_evidence_status_only_reflects_the_authenticated_students_own_submission(): void
    {
        $otherStudent = User::factory()->create(['school_grade_id' => $this->student->school_grade_id])->assignRole('student');
        $phase = $this->project->phases()->first();
        $evidence = ExpectedEvidence::factory()->create(['phase_id' => $phase->id]);
        Submission::factory()->create([
            'expected_evidence_id' => $evidence->id,
            'student_id' => $otherStudent->id,
        ]);

        $this->actingAs($this->student);
        $component = Livewire::test(ProjectShow::class, ['project' => $this->project]);

        $status = $component->instance()->evidenceStatus($evidence);

        $this->assertSame('pendiente', $status['status']);
    }

    /**
     * Hito de estrellas: ciclos 1-2 (Exploratorio, Conceptual) ven
     * <x-progress-stars>, nunca la barra ni el "{{ pct }}%" al mismo tiempo
     * -- User::isInEarlyCycle(). 47% (no múltiplo de 10) confirma también
     * el relleno parcial vía aria-label.
     */
    public function test_student_in_early_cycle_sees_stars_instead_of_the_bar(): void
    {
        $earlyCycle = Cycle::factory()->create(['order' => 1]);
        $grade = SchoolGrade::factory()->create(['cycle_id' => $earlyCycle->id]);
        $student = User::factory()->create(['school_grade_id' => $grade->id])->assignRole('student');
        $project = Project::factory()->create(['cycle_id' => $earlyCycle->id]);
        StudentProgress::factory()->create([
            'student_id' => $student->id,
            'project_id' => $project->id,
            'phase_id' => null,
            'progress_pct' => 47,
        ]);

        $response = $this->actingAs($student)->get(route('student.projects.show', $project->uuid));

        $response->assertOk();
        // Estrellas presentes (aria-label con el % real, para lectores de
        // pantalla) y NUNCA la barra ni su "47%" visible al mismo tiempo.
        $response->assertSee('aria-label="47% de avance"', false);
        $response->assertDontSee('<span class="text-gray-500">47%</span>', false);
        $response->assertDontSee('bg-emerald-500 h-2.5 rounded-full', false);
    }

    public function test_student_in_late_cycle_still_sees_the_numeric_bar(): void
    {
        $lateCycle = Cycle::factory()->create(['order' => 3]);
        $grade = SchoolGrade::factory()->create(['cycle_id' => $lateCycle->id]);
        $student = User::factory()->create(['school_grade_id' => $grade->id])->assignRole('student');
        $project = Project::factory()->create(['cycle_id' => $lateCycle->id]);
        StudentProgress::factory()->create([
            'student_id' => $student->id,
            'project_id' => $project->id,
            'phase_id' => null,
            'progress_pct' => 47,
        ]);

        $response = $this->actingAs($student)->get(route('student.projects.show', $project->uuid));

        $response->assertOk();
        $response->assertSee('<span class="text-gray-500">47%</span>', false);
        $response->assertSee('bg-emerald-500 h-2.5 rounded-full', false);
        $response->assertDontSee('aria-label="47% de avance"', false);
    }
}
