<?php

namespace Tests\Feature\ParentPortal;

use App\Models\User;
use App\Modules\Institution\Models\Cycle;
use App\Modules\Institution\Models\SchoolGrade;
use App\Modules\Institution\Models\ThinkingField;
use App\Modules\Project\Models\Project;
use App\Modules\Tracking\Models\StudentProgress;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Primer nivel del drill-down del acudiente (campo de pensamiento →
 * proyectos → detalle). isGuardianOf() se re-verifica en este componente
 * puntual, no solo confiado a una capa externa -- mismo criterio de
 * defensa en profundidad que ForumThreadShow/GroupChat con
 * hasRole('student').
 */
class ChildThinkingFieldsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_guardian_sees_their_childs_thinking_field_progress(): void
    {
        $guardian = User::factory()->create()->assignRole('parent');

        $cycle = Cycle::factory()->create();
        $grade = SchoolGrade::factory()->create(['cycle_id' => $cycle->id]);
        $child = User::factory()->create(['name' => 'Estudiante Ejemplo', 'school_grade_id' => $grade->id])->assignRole('student');
        $guardian->children()->attach($child->id, ['relationship' => 'padre']);

        $field = ThinkingField::factory()->create(['name' => 'Pensamiento matemático']);
        $project = Project::factory()->create(['cycle_id' => $cycle->id]);
        $project->thinkingFields()->attach($field->id);
        StudentProgress::factory()->create([
            'student_id' => $child->id,
            'project_id' => $project->id,
            'phase_id' => null,
            'progress_pct' => 40,
        ]);

        $response = $this->actingAs($guardian)->get(route('parent.child.fields', $child));

        $response->assertOk();
        $response->assertSee('Estudiante Ejemplo');
        $response->assertSee('Pensamiento matemático');
        $response->assertSee('40%');
    }

    public function test_guardian_cannot_access_a_child_that_is_not_theirs_via_direct_url(): void
    {
        $guardian = User::factory()->create()->assignRole('parent');
        $otherFamilysChild = User::factory()->create()->assignRole('student');

        $this->actingAs($guardian)
            ->get(route('parent.child.fields', $otherFamilysChild))
            ->assertForbidden();
    }

    public function test_student_cannot_access_the_route_middleware(): void
    {
        $student = User::factory()->create()->assignRole('student');
        $otherStudent = User::factory()->create()->assignRole('student');

        $this->actingAs($student)
            ->get(route('parent.child.fields', $otherStudent))
            ->assertForbidden();
    }

    public function test_teacher_cannot_access_the_route_middleware(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');
        $student = User::factory()->create()->assignRole('student');

        $this->actingAs($teacher)
            ->get(route('parent.child.fields', $student))
            ->assertForbidden();
    }
}
