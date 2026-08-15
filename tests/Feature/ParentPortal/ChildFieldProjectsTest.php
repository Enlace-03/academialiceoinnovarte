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
        $this->cycle = Cycle::factory()->create();
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
