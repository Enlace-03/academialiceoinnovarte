<?php

namespace Tests\Feature\ParentPortal;

use App\Livewire\Parent\ChildProjectShow;
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
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Tercer nivel del drill-down: mismo contenido de solo lectura que
 * Student\ProjectShow, pero para $child. Componente nuevo y separado
 * (decisión de la auditoría) -- test_component_has_no_writable_public_methods()
 * confirma por reflection, no solo por ausencia de botones en el Blade,
 * que no existe ningún método público capaz de mutar nada.
 */
class ChildProjectShowTest extends TestCase
{
    use RefreshDatabase;

    private User $guardian;

    private User $child;

    private Cycle $cycle;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLevelSeeder::class);

        $this->guardian = User::factory()->create()->assignRole('parent');
        $this->cycle = Cycle::factory()->create();
        $grade = SchoolGrade::factory()->create(['cycle_id' => $this->cycle->id]);
        $this->child = User::factory()->create(['school_grade_id' => $grade->id])->assignRole('student');
        $this->guardian->children()->attach($this->child->id, ['relationship' => 'padre']);
        $this->project = Project::factory()->create(['cycle_id' => $this->cycle->id, 'title' => 'Huerta escolar']);
    }

    public function test_guardian_can_view_their_childs_project(): void
    {
        $response = $this->actingAs($this->guardian)
            ->get(route('parent.child.project.show', ['child' => $this->child, 'project' => $this->project]));

        $response->assertOk();
        $response->assertSee('Huerta escolar');
    }

    public function test_shows_phases_guides_and_resources(): void
    {
        $phase = $this->project->phases()->first();
        Guide::factory()->create(['phase_id' => $phase->id, 'title' => 'Guía de siembra']);
        Resource::factory()->create(['phase_id' => $phase->id, 'guide_id' => null, 'title' => 'Video introductorio']);

        $response = $this->actingAs($this->guardian)
            ->get(route('parent.child.project.show', ['child' => $this->child, 'project' => $this->project]));

        $response->assertOk();
        $response->assertSee($phase->name);
        $response->assertSee('Guía de siembra');
        $response->assertSee('Video introductorio');
    }

    public function test_evidence_status_reflects_the_childs_own_submission_not_the_guardians(): void
    {
        $this->seed(RubricLevelSeeder::class);

        $phase = $this->project->phases()->first();
        $evidence = ExpectedEvidence::factory()->create(['phase_id' => $phase->id]);
        $submission = Submission::factory()->create([
            'expected_evidence_id' => $evidence->id,
            'student_id' => $this->child->id,
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

        $this->actingAs($this->guardian);
        $component = Livewire::test(ChildProjectShow::class, ['child' => $this->child, 'project' => $this->project]);

        $status = $component->instance()->evidenceStatus($evidence);

        $this->assertSame('evaluada', $status['status']);
        $this->assertTrue($status['level']->is($level));
        $this->assertSame('Excelente trabajo en equipo.', $status['feedback']);
    }

    public function test_evidence_status_of_another_students_submission_does_not_leak(): void
    {
        $otherStudent = User::factory()->create(['school_grade_id' => $this->child->school_grade_id])->assignRole('student');
        $phase = $this->project->phases()->first();
        $evidence = ExpectedEvidence::factory()->create(['phase_id' => $phase->id]);
        Submission::factory()->create([
            'expected_evidence_id' => $evidence->id,
            'student_id' => $otherStudent->id,
        ]);

        $this->actingAs($this->guardian);
        $component = Livewire::test(ChildProjectShow::class, ['child' => $this->child, 'project' => $this->project]);

        $status = $component->instance()->evidenceStatus($evidence);

        $this->assertSame('pendiente', $status['status']);
    }

    public function test_guardian_cannot_access_a_child_that_is_not_theirs_via_direct_url(): void
    {
        $otherFamilysChild = User::factory()->create(['school_grade_id' => $this->child->school_grade_id])->assignRole('student');

        $this->actingAs($this->guardian)
            ->get(route('parent.child.project.show', ['child' => $otherFamilysChild, 'project' => $this->project]))
            ->assertForbidden();
    }

    public function test_guardian_cannot_access_a_draft_project(): void
    {
        $draft = Project::factory()->draft()->create(['cycle_id' => $this->cycle->id]);

        $this->actingAs($this->guardian)
            ->get(route('parent.child.project.show', ['child' => $this->child, 'project' => $draft]))
            ->assertForbidden();
    }

    public function test_guardian_cannot_access_a_project_outside_the_childs_cycle(): void
    {
        $otherCycle = Cycle::factory()->create();
        $projectInOtherCycle = Project::factory()->create(['cycle_id' => $otherCycle->id]);

        $this->actingAs($this->guardian)
            ->get(route('parent.child.project.show', ['child' => $this->child, 'project' => $projectInOtherCycle]))
            ->assertForbidden();
    }

    /**
     * Hito de estrellas: hijo de ciclo 1-2 ve <x-progress-stars>, nunca la
     * barra ni el "{{ pct }}%" al mismo tiempo.
     */
    public function test_guardian_sees_stars_for_a_child_in_an_early_cycle(): void
    {
        $earlyCycle = Cycle::factory()->create(['order' => 1]);
        $grade = SchoolGrade::factory()->create(['cycle_id' => $earlyCycle->id]);
        $child = User::factory()->create(['school_grade_id' => $grade->id])->assignRole('student');
        $this->guardian->children()->attach($child->id, ['relationship' => 'madre']);
        $project = Project::factory()->create(['cycle_id' => $earlyCycle->id]);
        StudentProgress::factory()->create([
            'student_id' => $child->id,
            'project_id' => $project->id,
            'phase_id' => null,
            'progress_pct' => 47,
        ]);

        $response = $this->actingAs($this->guardian)
            ->get(route('parent.child.project.show', ['child' => $child, 'project' => $project]));

        $response->assertOk();
        $response->assertSee('aria-label="47% de avance"', false);
        $response->assertDontSee('<span class="text-gray-500">47%</span>', false);
        $response->assertDontSee('bg-emerald-500 h-2.5 rounded-full', false);
    }

    public function test_guardian_still_sees_the_numeric_bar_for_a_child_in_a_late_cycle(): void
    {
        $lateCycle = Cycle::factory()->create(['order' => 4]);
        $grade = SchoolGrade::factory()->create(['cycle_id' => $lateCycle->id]);
        $child = User::factory()->create(['school_grade_id' => $grade->id])->assignRole('student');
        $this->guardian->children()->attach($child->id, ['relationship' => 'madre']);
        $project = Project::factory()->create(['cycle_id' => $lateCycle->id]);
        StudentProgress::factory()->create([
            'student_id' => $child->id,
            'project_id' => $project->id,
            'phase_id' => null,
            'progress_pct' => 47,
        ]);

        $response = $this->actingAs($this->guardian)
            ->get(route('parent.child.project.show', ['child' => $child, 'project' => $project]));

        $response->assertOk();
        $response->assertSee('<span class="text-gray-500">47%</span>', false);
        $response->assertSee('bg-emerald-500 h-2.5 rounded-full', false);
        $response->assertDontSee('aria-label="47% de avance"', false);
    }

    public function test_student_cannot_access_the_route_middleware(): void
    {
        $this->actingAs($this->child)
            ->get(route('parent.child.project.show', ['child' => $this->child, 'project' => $this->project]))
            ->assertForbidden();
    }

    /**
     * Confirmación explícita, por reflection -- no solo por ausencia de
     * botones en el Blade -- de que ningún método público puede mutar
     * nada de $child ni de $project. Cualquier método de escritura
     * agregado a futuro rompe este test y obliga a una revisión explícita.
     */
    public function test_component_has_no_writable_public_methods(): void
    {
        $publicMethods = collect((new ReflectionClass(ChildProjectShow::class))->getMethods(ReflectionMethod::IS_PUBLIC))
            ->filter(fn (ReflectionMethod $method): bool => $method->getDeclaringClass()->getName() === ChildProjectShow::class)
            ->map(fn (ReflectionMethod $method): string => $method->getName())
            ->values()
            ->all();

        $this->assertEqualsCanonicalizing(
            ['mount', 'phases', 'evidenceStatus', 'progressSummary', 'render'],
            $publicMethods
        );
    }
}
