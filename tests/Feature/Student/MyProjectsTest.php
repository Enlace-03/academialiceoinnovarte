<?php

namespace Tests\Feature\Student;

use App\Models\User;
use App\Modules\Institution\Models\Cycle;
use App\Modules\Institution\Models\SchoolGrade;
use App\Modules\Project\Models\Project;
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

    public function test_student_without_school_grade_sees_an_empty_list(): void
    {
        $student = User::factory()->create(['school_grade_id' => null])->assignRole('student');
        Project::factory()->create();

        $response = $this->actingAs($student)->get(route('student.projects.index'));

        $response->assertOk();
        $response->assertSee('Todavía no hay proyectos disponibles para tu ciclo.');
    }
}
