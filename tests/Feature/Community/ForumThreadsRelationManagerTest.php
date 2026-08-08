<?php

namespace Tests\Feature\Community;

use App\Filament\Academic\Resources\Projects\Pages\EditProject;
use App\Filament\Academic\Resources\Projects\RelationManagers\ForumThreadsRelationManager;
use App\Models\User;
use App\Modules\Community\Models\ForumThread;
use App\Modules\Project\Models\Project;
use Database\Seeders\RoleLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Ejercita el camino real del RelationManager (no llamadas directas a la
 * Action) para confirmar que la UI de /academia respeta exactamente lo que
 * ForumThreadPolicy ya decide: un docente modera sus propios proyectos, no
 * los ajenos.
 */
class ForumThreadsRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLevelSeeder::class);

        Filament::setCurrentPanel(Filament::getPanel('academic'));
    }

    public function test_teacher_can_create_a_thread_in_own_project(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');
        $this->actingAs($teacher);
        $project = Project::factory()->create(['created_by_user_id' => $teacher->id]);

        Livewire::test(ForumThreadsRelationManager::class, [
            'ownerRecord' => $project,
            'pageClass' => EditProject::class,
        ])
            ->mountTableAction('create')
            ->setTableActionData(['title' => 'Bienvenida al proyecto', 'phase_id' => null])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('forum_threads', [
            'project_id' => $project->id,
            'title' => 'Bienvenida al proyecto',
            'created_by' => $teacher->id,
        ]);
    }

    public function test_teacher_can_hide_a_thread_in_own_project(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');
        $this->actingAs($teacher);
        $project = Project::factory()->create(['created_by_user_id' => $teacher->id]);
        $thread = ForumThread::factory()->create(['project_id' => $project->id]);

        Livewire::test(ForumThreadsRelationManager::class, [
            'ownerRecord' => $project,
            'pageClass' => EditProject::class,
        ])
            ->mountTableAction('hide', $thread)
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertTrue($thread->fresh()->is_hidden);
        $this->assertSame($teacher->id, $thread->fresh()->hidden_by_user_id);
    }

    public function test_teacher_cannot_hide_a_thread_in_another_teachers_project(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');
        $this->actingAs($teacher);

        $otherTeacher = User::factory()->create()->assignRole('teacher');
        $otherProject = Project::factory()->create(['created_by_user_id' => $otherTeacher->id]);
        $thread = ForumThread::factory()->create(['project_id' => $otherProject->id]);

        // El propio ProjectResource ya oculta proyectos ajenos del listado
        // (getEloquentQuery scoping); esto confirma que, aun con el registro
        // en mano, la acción de ocultar queda oculta por la Policy.
        Livewire::test(ForumThreadsRelationManager::class, [
            'ownerRecord' => $otherProject,
            'pageClass' => EditProject::class,
        ])
            ->assertTableActionHidden('hide', $thread);

        $this->assertFalse($thread->fresh()->is_hidden);
    }
}
