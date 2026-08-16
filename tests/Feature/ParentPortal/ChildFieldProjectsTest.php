<?php

namespace Tests\Feature\ParentPortal;

use App\Models\User;
use App\Modules\Institution\Models\Cycle;
use App\Modules\Institution\Models\SchoolGrade;
use App\Modules\Institution\Models\ThinkingField;
use App\Modules\Project\Models\Project;
use App\Modules\Tracking\Models\PerformanceSnapshot;
use App\Modules\Tracking\Models\StudentProgress;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RubricLevelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Segundo nivel del drill-down: proyectos publicados que tocan el campo
 * seleccionado, con barra + nivel cualitativo por proyecto (dos
 * indicadores separados, mismo criterio que ProjectShow).
 */
class ChildFieldProjectsTest extends TestCase
{
    use RefreshDatabase;

    private User $guardian;

    private User $child;

    private Cycle $cycle;

    private ThinkingField $field;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RubricLevelSeeder::class);

        $this->guardian = User::factory()->create()->assignRole('parent');
        // order=3 (ciclo tardío) explícito: esta clase verifica la barra
        // numérica de siempre -- el reemplazo por estrellas en ciclos 1-2
        // tiene su propia cobertura dedicada (Hito de estrellas). Sin
        // fijarlo, Cycle::factory() cae en un order aleatorio 1-4 y
        // test_lists_published_projects_touching_the_field_with_progress_and_level
        // queda intermitente (assertSee('55%')).
        $this->cycle = Cycle::factory()->create(['order' => 3]);
        $grade = SchoolGrade::factory()->create(['cycle_id' => $this->cycle->id]);
        $this->child = User::factory()->create(['school_grade_id' => $grade->id])->assignRole('student');
        $this->guardian->children()->attach($this->child->id, ['relationship' => 'madre']);
        $this->field = ThinkingField::factory()->create();
    }

    public function test_lists_published_projects_touching_the_field_with_progress_and_level(): void
    {
        $project = Project::factory()->create(['cycle_id' => $this->cycle->id, 'title' => 'Huerta escolar']);
        $project->thinkingFields()->attach($this->field->id);
        StudentProgress::factory()->create([
            'student_id' => $this->child->id,
            'project_id' => $project->id,
            'phase_id' => null,
            'progress_pct' => 55,
        ]);
        PerformanceSnapshot::factory()->create([
            'student_id' => $this->child->id,
            'project_id' => $project->id,
            'metrics' => ['progress_pct' => 55, 'qualitative_level_key' => 'logro_esperado'],
        ]);

        $response = $this->actingAs($this->guardian)
            ->get(route('parent.child.field.projects', ['child' => $this->child, 'field' => $this->field]));

        $response->assertOk();
        $response->assertSee('Huerta escolar');
        $response->assertSee('55%');
        $response->assertSee('Logro esperado');
    }

    /**
     * Hito de estrellas: hijo de ciclo 1-2 ve <x-progress-stars> por
     * proyecto en esta lista, nunca la barra ni el "{{ pct }}%" al mismo
     * tiempo. Cycle/child propios (no $this->cycle/$this->child, fijados a
     * ciclo tardío en setUp() a propósito).
     */
    public function test_guardian_sees_stars_for_a_child_in_an_early_cycle(): void
    {
        $guardian = User::factory()->create()->assignRole('parent');
        $earlyCycle = Cycle::factory()->create(['order' => 1]);
        $grade = SchoolGrade::factory()->create(['cycle_id' => $earlyCycle->id]);
        $child = User::factory()->create(['school_grade_id' => $grade->id])->assignRole('student');
        $guardian->children()->attach($child->id, ['relationship' => 'madre']);
        $field = ThinkingField::factory()->create();

        $project = Project::factory()->create(['cycle_id' => $earlyCycle->id, 'title' => 'Huerta escolar temprana']);
        $project->thinkingFields()->attach($field->id);
        StudentProgress::factory()->create([
            'student_id' => $child->id,
            'project_id' => $project->id,
            'phase_id' => null,
            'progress_pct' => 47,
        ]);

        $response = $this->actingAs($guardian)
            ->get(route('parent.child.field.projects', ['child' => $child, 'field' => $field]));

        $response->assertOk();
        $response->assertSee('aria-label="47% de avance"', false);
        $response->assertDontSee('bg-emerald-500 h-2 rounded-full', false);
    }

    public function test_project_not_touching_the_field_is_not_listed(): void
    {
        $otherField = ThinkingField::factory()->create();
        $project = Project::factory()->create(['cycle_id' => $this->cycle->id, 'title' => 'Proyecto de otro campo']);
        $project->thinkingFields()->attach($otherField->id);

        $response = $this->actingAs($this->guardian)
            ->get(route('parent.child.field.projects', ['child' => $this->child, 'field' => $this->field]));

        $response->assertOk();
        $response->assertDontSee('Proyecto de otro campo');
    }

    public function test_draft_project_touching_the_field_is_not_listed(): void
    {
        $draft = Project::factory()->draft()->create(['cycle_id' => $this->cycle->id, 'title' => 'Proyecto borrador']);
        $draft->thinkingFields()->attach($this->field->id);

        $response = $this->actingAs($this->guardian)
            ->get(route('parent.child.field.projects', ['child' => $this->child, 'field' => $this->field]));

        $response->assertOk();
        $response->assertDontSee('Proyecto borrador');
    }

    public function test_guardian_cannot_access_a_child_that_is_not_theirs_via_direct_url(): void
    {
        $otherFamilysChild = User::factory()->create()->assignRole('student');

        $this->actingAs($this->guardian)
            ->get(route('parent.child.field.projects', ['child' => $otherFamilysChild, 'field' => $this->field]))
            ->assertForbidden();
    }
}
