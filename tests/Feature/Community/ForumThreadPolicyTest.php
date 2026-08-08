<?php

namespace Tests\Feature\Community;

use App\Models\User;
use App\Modules\Community\Models\ForumThread;
use App\Modules\Institution\Models\Cycle;
use App\Modules\Institution\Models\SchoolGrade;
use App\Modules\Project\Models\Project;
use Database\Seeders\RoleLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El caso más sensible del Hito 3: un estudiante NUNCA debe ver ni crear
 * contenido de un proyecto de un ciclo que no es el suyo, sin importar quién
 * sea el dueño del proyecto o si el hilo está oculto.
 */
class ForumThreadPolicyTest extends TestCase
{
    use RefreshDatabase;

    private Project $ownCycleProject;

    private Project $otherCycleProject;

    private User $studentOwnCycle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLevelSeeder::class);

        $ownCycle = Cycle::factory()->create();
        $otherCycle = Cycle::factory()->create();

        $ownGrade = SchoolGrade::factory()->create(['cycle_id' => $ownCycle->id]);

        $this->ownCycleProject = Project::factory()->create(['cycle_id' => $ownCycle->id]);
        $this->otherCycleProject = Project::factory()->create(['cycle_id' => $otherCycle->id]);

        $this->studentOwnCycle = User::factory()->create(['school_grade_id' => $ownGrade->id])
            ->assignRole('student');
    }

    public function test_student_can_view_thread_in_own_cycle_project(): void
    {
        $thread = ForumThread::factory()->create(['project_id' => $this->ownCycleProject->id]);

        $this->assertTrue($this->studentOwnCycle->can('view', $thread));
    }

    public function test_student_cannot_view_thread_in_other_cycle_project(): void
    {
        $thread = ForumThread::factory()->create(['project_id' => $this->otherCycleProject->id]);

        $this->assertFalse($this->studentOwnCycle->can('view', $thread));
    }

    public function test_student_without_school_grade_cannot_view_any_thread(): void
    {
        $studentWithoutGrade = User::factory()->create(['school_grade_id' => null])->assignRole('student');
        $thread = ForumThread::factory()->create(['project_id' => $this->ownCycleProject->id]);

        $this->assertFalse($studentWithoutGrade->can('view', $thread));
    }

    public function test_student_cannot_view_hidden_thread_even_in_own_cycle(): void
    {
        $thread = ForumThread::factory()->create([
            'project_id' => $this->ownCycleProject->id,
            'is_hidden' => true,
        ]);

        $this->assertFalse($this->studentOwnCycle->can('view', $thread));
    }

    public function test_parent_cannot_view_any_thread_fail_closed(): void
    {
        // Sin diseño resuelto todavía de acceso del acudiente en nombre del
        // hijo (TODO.md #13) -- debe fallar cerrado, no heredar acceso.
        $parent = User::factory()->create()->assignRole('parent');
        $thread = ForumThread::factory()->create(['project_id' => $this->ownCycleProject->id]);

        $this->assertFalse($parent->can('view', $thread));
    }

    public function test_teacher_can_view_thread_in_own_project(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');
        $ownProject = Project::factory()->create(['created_by_user_id' => $teacher->id]);
        $thread = ForumThread::factory()->create(['project_id' => $ownProject->id]);

        $this->assertTrue($teacher->can('view', $thread));
    }

    public function test_teacher_cannot_view_thread_in_other_teachers_project(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');
        $otherTeacher = User::factory()->create()->assignRole('teacher');
        $otherProject = Project::factory()->create(['created_by_user_id' => $otherTeacher->id]);
        $thread = ForumThread::factory()->create(['project_id' => $otherProject->id]);

        $this->assertFalse($teacher->can('view', $thread));
    }

    public function test_teacher_still_sees_a_hidden_thread_in_own_project(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');
        $ownProject = Project::factory()->create(['created_by_user_id' => $teacher->id]);
        $thread = ForumThread::factory()->create(['project_id' => $ownProject->id, 'is_hidden' => true]);

        $this->assertTrue($teacher->can('view', $thread));
    }

    public function test_rector_can_view_any_thread(): void
    {
        $rector = User::factory()->create()->assignRole('rector');
        $thread = ForumThread::factory()->create(['project_id' => $this->otherCycleProject->id]);

        $this->assertTrue($rector->can('view', $thread));
    }

    public function test_student_can_create_thread_in_own_cycle_project(): void
    {
        $this->assertTrue($this->studentOwnCycle->can('create', [ForumThread::class, $this->ownCycleProject]));
    }

    public function test_student_cannot_create_thread_in_other_cycle_project(): void
    {
        $this->assertFalse($this->studentOwnCycle->can('create', [ForumThread::class, $this->otherCycleProject]));
    }

    public function test_teacher_can_create_thread_in_own_project(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');
        $ownProject = Project::factory()->create(['created_by_user_id' => $teacher->id]);

        $this->assertTrue($teacher->can('create', [ForumThread::class, $ownProject]));
    }

    public function test_student_can_never_hide_a_thread(): void
    {
        $thread = ForumThread::factory()->create(['project_id' => $this->ownCycleProject->id]);

        $this->assertFalse($this->studentOwnCycle->can('hide', $thread));
    }

    public function test_teacher_can_hide_thread_in_own_project(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');
        $ownProject = Project::factory()->create(['created_by_user_id' => $teacher->id]);
        $thread = ForumThread::factory()->create(['project_id' => $ownProject->id]);

        $this->assertTrue($teacher->can('hide', $thread));
    }

    public function test_teacher_cannot_hide_thread_in_other_teachers_project(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');
        $otherTeacher = User::factory()->create()->assignRole('teacher');
        $otherProject = Project::factory()->create(['created_by_user_id' => $otherTeacher->id]);
        $thread = ForumThread::factory()->create(['project_id' => $otherProject->id]);

        $this->assertFalse($teacher->can('hide', $thread));
    }

    public function test_rector_can_hide_any_thread(): void
    {
        $rector = User::factory()->create()->assignRole('rector');
        $thread = ForumThread::factory()->create(['project_id' => $this->otherCycleProject->id]);

        $this->assertTrue($rector->can('hide', $thread));
    }
}
