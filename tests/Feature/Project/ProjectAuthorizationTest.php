<?php

namespace Tests\Feature\Project;

use App\Models\User;
use App\Modules\Project\Models\Project;
use Database\Seeders\RoleLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLevelSeeder::class);
    }

    public function test_user_without_any_project_permission_cannot_view_the_resource_index(): void
    {
        // secretary tiene rol (entra a /academia) pero ningún permiso de proyectos.
        $secretary = User::factory()->create()->assignRole('secretary');

        $this->actingAs($secretary)
            ->get(route('filament.academic.resources.projects.index'))
            ->assertForbidden();
    }

    public function test_teacher_can_view_the_resource_index(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');

        $this->actingAs($teacher)
            ->get(route('filament.academic.resources.projects.index'))
            ->assertOk();
    }

    /**
     * El panel /academia es accesible para cualquier usuario con rol
     * (canAccessPanel solo exige roles()->exists()), incluidos student y
     * parent. ProjectResource debe quedar completamente oculto para ellos:
     * ni en la navegación, ni accesible por URL directa.
     */
    public function test_student_and_parent_cannot_view_the_resource_index_and_it_is_hidden_from_navigation(): void
    {
        $student = User::factory()->create()->assignRole('student');
        $parent = User::factory()->create()->assignRole('parent');

        foreach ([$student, $parent] as $user) {
            $this->actingAs($user);

            $this->assertFalse(
                \App\Filament\Academic\Resources\Projects\ProjectResource::canAccess(),
                'canAccess() debe ser false, lo que también oculta el ítem de navegación.'
            );

            $this->get(route('filament.academic.resources.projects.index'))
                ->assertForbidden();
        }
    }

    public function test_teacher_can_view_own_project_but_not_someone_elses(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');
        $otherTeacher = User::factory()->create()->assignRole('teacher');

        $ownProject = Project::factory()->create(['created_by_user_id' => $teacher->id]);
        $otherProject = Project::factory()->create(['created_by_user_id' => $otherTeacher->id]);

        $this->assertTrue($teacher->can('view', $ownProject));
        $this->assertFalse($teacher->can('view', $otherProject));
    }

    public function test_rector_can_view_any_project(): void
    {
        $rector = User::factory()->create()->assignRole('rector');
        $teacher = User::factory()->create()->assignRole('teacher');
        $project = Project::factory()->create(['created_by_user_id' => $teacher->id]);

        $this->assertTrue($rector->can('view', $project));
    }

    public function test_teacher_can_update_own_project_but_not_someone_elses(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');
        $otherTeacher = User::factory()->create()->assignRole('teacher');

        $ownProject = Project::factory()->create(['created_by_user_id' => $teacher->id]);
        $otherProject = Project::factory()->create(['created_by_user_id' => $otherTeacher->id]);

        $this->assertTrue($teacher->can('update', $ownProject));
        $this->assertFalse($teacher->can('update', $otherProject));
    }

    /**
     * phases.manage NO es una puerta independiente: un docente con ese
     * permiso global no puede tocar las fases de un proyecto que no creó.
     */
    public function test_phases_manage_permission_alone_does_not_authorize_a_project_the_teacher_does_not_own(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');
        $otherTeacher = User::factory()->create()->assignRole('teacher');
        $otherProject = Project::factory()->create(['created_by_user_id' => $otherTeacher->id]);

        $this->assertTrue($teacher->hasPermissionTo('phases.manage'));
        $this->assertFalse($teacher->can('managePhases', $otherProject));
    }

    public function test_phases_manage_permission_authorizes_the_teachers_own_project(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');
        $ownProject = Project::factory()->create(['created_by_user_id' => $teacher->id]);

        $this->assertTrue($teacher->can('managePhases', $ownProject));
    }

    public function test_resources_manage_permission_alone_does_not_authorize_a_project_the_teacher_does_not_own(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');
        $otherTeacher = User::factory()->create()->assignRole('teacher');
        $otherProject = Project::factory()->create(['created_by_user_id' => $otherTeacher->id]);

        $this->assertTrue($teacher->hasPermissionTo('resources.manage'));
        $this->assertFalse($teacher->can('manageResources', $otherProject));
    }

    public function test_coordinator_has_the_same_five_project_permissions_as_rector(): void
    {
        $rector = User::factory()->create()->assignRole('rector');
        $coordinator = User::factory()->create()->assignRole('coordinator');

        $projectPermissions = [
            'projects.view.all', 'projects.create', 'projects.update.all',
            'phases.manage', 'resources.manage',
        ];

        foreach ($projectPermissions as $permission) {
            $this->assertTrue($rector->hasPermissionTo($permission));
            $this->assertTrue($coordinator->hasPermissionTo($permission));
        }
    }
}
