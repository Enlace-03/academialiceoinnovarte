<?php

namespace Tests\Feature\Institution;

use App\Filament\Academic\Resources\Groups\GroupResource;
use App\Models\User;
use App\Modules\Community\Models\ChatMessage;
use App\Modules\Institution\Models\Group;
use Database\Seeders\RoleLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Autorización de "Entregar sesión" (Hito 3b-2): deliberadamente tan amplia
 * como ChatMessagePolicy ya documenta en TODO.md #15 -- cualquier staff,
 * cualquier grupo, no acotado a "el grupo de ESE docente" (teacher_assignments
 * sigue siendo scaffolding huérfano). No se restringió más aquí a propósito:
 * la instrucción explícita fue reutilizar el criterio existente, no crear
 * uno nuevo -- eso incluye a secretary/super_admin (categoría staff), no
 * solo a teacher/coordinator/rector.
 */
class AcademicGroupResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLevelSeeder::class);

        Filament::setCurrentPanel(Filament::getPanel('academic'));
    }

    public function test_teacher_can_view_the_groups_list(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');
        $this->actingAs($teacher);

        $this->assertTrue(GroupResource::canViewAny());
    }

    public function test_student_cannot_view_the_groups_list(): void
    {
        $student = User::factory()->create()->assignRole('student');
        $this->actingAs($student);

        $this->assertFalse(GroupResource::canViewAny());
    }

    public function test_parent_cannot_view_the_groups_list(): void
    {
        $parent = User::factory()->create()->assignRole('parent');
        $this->actingAs($parent);

        $this->assertFalse(GroupResource::canViewAny());
    }

    /**
     * Página plana fuera de Filament/Livewire a propósito (ver docblock de
     * GroupsTable::grantSessionAction() y de las rutas academic.group-sessions.*
     * en routes/web.php): un <form> HTML normal, sin ningún ciclo de vida de
     * Livewire de por medio en el paso que cambia de identidad.
     */
    public function test_teacher_unrelated_to_the_group_can_still_grant_a_session_broad_scope_by_design(): void
    {
        $group = Group::factory()->create();
        $teacher = User::factory()->create()->assignRole('teacher'); // sin relación con $group
        $student = User::factory()->create(['group_id' => $group->id])->assignRole('student');

        $this->actingAs($teacher);

        $this->get(route('academic.group-sessions.create', $group))
            ->assertOk()
            ->assertSee($student->name);

        $this->post(route('academic.group-sessions.store', $group), ['student_id' => $student->id])
            ->assertRedirect(route('student.projects.index'));

        $this->assertDatabaseHas('student_session_grants', [
            'student_id' => $student->id,
            'granted_by_user_id' => $teacher->id,
            'group_id' => $group->id,
        ]);
        $this->assertAuthenticatedAs($student);
    }

    public function test_granting_a_session_for_a_student_of_another_group_is_rejected(): void
    {
        $group = Group::factory()->create();
        $otherGroup = Group::factory()->create();
        $teacher = User::factory()->create()->assignRole('teacher');
        $student = User::factory()->create(['group_id' => $otherGroup->id])->assignRole('student');

        $this->actingAs($teacher);

        $this->post(route('academic.group-sessions.store', $group), ['student_id' => $student->id])
            ->assertStatus(422);

        $this->assertDatabaseMissing('student_session_grants', ['student_id' => $student->id]);
        $this->assertAuthenticatedAs($teacher);
    }

    public function test_student_cannot_access_the_grant_session_form(): void
    {
        $group = Group::factory()->create();
        $otherStudent = User::factory()->create()->assignRole('student');
        $this->actingAs($otherStudent);

        $this->get(route('academic.group-sessions.create', $group))->assertForbidden();
    }

    public function test_grant_session_criterion_is_as_broad_as_isStaff_not_limited_to_teacher_coordinator_rector(): void
    {
        $group = Group::factory()->create();
        $secretary = User::factory()->create()->assignRole('secretary');
        $this->actingAs($secretary);

        $this->assertTrue(Gate::allows('create', [ChatMessage::class, $group]));
    }
}
