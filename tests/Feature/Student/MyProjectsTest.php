<?php

namespace Tests\Feature\Student;

use App\Models\User;
use App\Modules\Institution\Models\Cycle;
use App\Modules\Institution\Models\SchoolGrade;
use App\Modules\Institution\Models\ThinkingField;
use App\Modules\Project\Models\Project;
use App\Modules\Tracking\Models\StudentProgress;
use Database\Seeders\RoleLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyProjectsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLevelSeeder::class);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('student.projects.index'))->assertRedirect(route('login'));
    }

    public function test_teacher_cannot_access_the_student_portal(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');

        $this->actingAs($teacher)
            ->get(route('student.projects.index'))
            ->assertForbidden();
    }

    public function test_parent_cannot_access_the_student_portal(): void
    {
        $parent = User::factory()->create()->assignRole('parent');

        $this->actingAs($parent)
            ->get(route('student.projects.index'))
            ->assertForbidden();
    }

    public function test_student_sees_only_projects_of_own_cycle(): void
    {
        $ownCycle = Cycle::factory()->create();
        $otherCycle = Cycle::factory()->create();
        $grade = SchoolGrade::factory()->create(['cycle_id' => $ownCycle->id]);
        $student = User::factory()->create(['school_grade_id' => $grade->id])->assignRole('student');

        Project::factory()->create(['cycle_id' => $ownCycle->id, 'title' => 'Proyecto de mi ciclo']);
        Project::factory()->create(['cycle_id' => $otherCycle->id, 'title' => 'Proyecto de otro ciclo']);

        $response = $this->actingAs($student)->get(route('student.projects.index'));

        $response->assertOk();
        $response->assertSee('Proyecto de mi ciclo');
        $response->assertDontSee('Proyecto de otro ciclo');
    }

    /**
     * MyProjects no pasa por ProjectPolicy (es un query directo por
     * cycle_id) -- este filtro es explícito en la propia query, ver
     * MyProjects::projects().
     */
    public function test_student_does_not_see_draft_projects_of_own_cycle(): void
    {
        $cycle = Cycle::factory()->create();
        $grade = SchoolGrade::factory()->create(['cycle_id' => $cycle->id]);
        $student = User::factory()->create(['school_grade_id' => $grade->id])->assignRole('student');

        Project::factory()->create(['cycle_id' => $cycle->id, 'title' => 'Proyecto publicado']);
        Project::factory()->draft()->create(['cycle_id' => $cycle->id, 'title' => 'Proyecto en borrador']);

        $response = $this->actingAs($student)->get(route('student.projects.index'));

        $response->assertOk();
        $response->assertSee('Proyecto publicado');
        $response->assertDontSee('Proyecto en borrador');
    }

    /**
     * Hito 4b: el bloque de barras por campo de pensamiento aparece en el
     * mismo home del estudiante donde ya está la lista de proyectos --
     * cálculo real ya cubierto a fondo en AggregateThinkingFieldProgressActionTest,
     * acá solo se confirma que se renderiza en esta pantalla.
     */
    public function test_shows_the_thinking_field_progress_block(): void
    {
        // order=3 (ciclo tardío) explícito: este test verifica la barra
        // numérica de siempre -- el reemplazo por estrellas en ciclos 1-2
        // tiene su propia cobertura dedicada (Hito de estrellas). Sin
        // fijarlo, Cycle::factory() cae en un order aleatorio 1-4.
        $cycle = Cycle::factory()->create(['order' => 3]);
        $grade = SchoolGrade::factory()->create(['cycle_id' => $cycle->id]);
        $student = User::factory()->create(['school_grade_id' => $grade->id])->assignRole('student');

        $field = ThinkingField::factory()->create(['name' => 'Campo de prueba']);
        $project = Project::factory()->create(['cycle_id' => $cycle->id]);
        $project->thinkingFields()->attach($field->id);
        StudentProgress::factory()->create([
            'student_id' => $student->id,
            'project_id' => $project->id,
            'phase_id' => null,
            'progress_pct' => 42,
        ]);

        $response = $this->actingAs($student)->get(route('student.projects.index'));

        $response->assertOk();
        $response->assertSee('Avance por campo de pensamiento');
        $response->assertSee('Campo de prueba');
        $response->assertSee('42%');
    }

    /**
     * Hito de estrellas: estudiante de ciclo 1-2 ve <x-progress-stars> en
     * este mismo bloque, nunca la barra ni el "{{ pct }}%" al mismo tiempo.
     */
    public function test_student_in_early_cycle_sees_stars_in_the_thinking_field_progress_block(): void
    {
        $earlyCycle = Cycle::factory()->create(['order' => 1]);
        $grade = SchoolGrade::factory()->create(['cycle_id' => $earlyCycle->id]);
        $student = User::factory()->create(['school_grade_id' => $grade->id])->assignRole('student');

        $field = ThinkingField::factory()->create(['name' => 'Campo temprano']);
        $project = Project::factory()->create(['cycle_id' => $earlyCycle->id]);
        $project->thinkingFields()->attach($field->id);
        StudentProgress::factory()->create([
            'student_id' => $student->id,
            'project_id' => $project->id,
            'phase_id' => null,
            'progress_pct' => 47,
        ]);

        $response = $this->actingAs($student)->get(route('student.projects.index'));

        $response->assertOk();
        $response->assertSee('aria-label="47% de avance"', false);
        $response->assertDontSee('<span class="text-gray-500">47%</span>', false);
        $response->assertDontSee('bg-emerald-500 h-2.5 rounded-full', false);
    }

    public function test_student_without_school_grade_sees_an_empty_list(): void
    {
        $student = User::factory()->create(['school_grade_id' => null])->assignRole('student');
        Project::factory()->create();

        $response = $this->actingAs($student)->get(route('student.projects.index'));

        $response->assertOk();
        $response->assertSee('Todavía no hay proyectos disponibles para tu ciclo.');
    }
}
