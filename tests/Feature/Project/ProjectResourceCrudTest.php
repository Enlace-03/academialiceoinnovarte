<?php

namespace Tests\Feature\Project;

use App\Filament\Academic\Resources\Projects\Pages\CreateProject;
use App\Filament\Academic\Resources\Projects\Pages\EditProject;
use App\Filament\Academic\Resources\Projects\Pages\ListProjects;
use App\Filament\Academic\Resources\Projects\Pages\ViewProject;
use App\Filament\Academic\Resources\Projects\ProjectResource;
use App\Models\User;
use App\Modules\Institution\Models\Cycle;
use App\Modules\Project\Models\Project;
use Database\Seeders\RoleLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProjectResourceCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLevelSeeder::class);

        $this->actingAs(User::factory()->create()->assignRole('teacher'));
        Filament::setCurrentPanel(Filament::getPanel('academic'));
    }

    public function test_projects_list_loads(): void
    {
        Project::factory()->create(['created_by_user_id' => auth()->id()]);

        Livewire::test(ListProjects::class)->assertOk();
    }

    public function test_creating_a_project_sets_created_by_and_generates_the_four_phases(): void
    {
        $cycle = Cycle::factory()->create();

        Livewire::test(CreateProject::class)
            ->fillForm([
                'cycle_id' => $cycle->id,
                'title' => 'El agua que compartimos',
                'semester' => 1,
                'year' => (int) config('school.current_academic_year'),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $project = Project::where('title', 'El agua que compartimos')->first();

        $this->assertNotNull($project);
        $this->assertSame(auth()->id(), $project->created_by_user_id);
        $this->assertCount(4, $project->phases);
    }

    /**
     * status no viene del formulario (ProjectForm no lo expone) -- un
     * proyecto recién creado por Filament nace en el default real de la
     * columna ('draft', migración 2027_01_01_000410), no en el default de
     * ProjectFactory (que es 'published' a propósito, solo para
     * conveniencia de otros tests -- ver docblock de la factory).
     */
    public function test_a_newly_created_project_starts_as_draft(): void
    {
        $cycle = Cycle::factory()->create();

        Livewire::test(CreateProject::class)
            ->fillForm([
                'cycle_id' => $cycle->id,
                'title' => 'Proyecto nuevo',
                'semester' => 1,
                'year' => (int) config('school.current_academic_year'),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('projects', ['title' => 'Proyecto nuevo', 'status' => 'draft']);
    }

    /**
     * Separación vista/edición: clic en una fila de ListProjects debe
     * aterrizar en ViewProject, no en EditProject directo -- ProjectsTable
     * no registra recordActions(), así que la única forma real de verificar
     * "adónde lleva el clic" es inspeccionar el href real que renderiza la
     * fila (Filament no expone un helper de test para recordUrl -- ver el
     * hallazgo de investigación de este mismo hito).
     */
    public function test_clicking_a_project_row_links_to_view_not_edit(): void
    {
        $project = Project::factory()->create(['created_by_user_id' => auth()->id()]);

        $viewUrl = ProjectResource::getUrl('view', ['record' => $project]);
        $editUrl = ProjectResource::getUrl('edit', ['record' => $project]);

        $html = Livewire::test(ListProjects::class)->html();

        $this->assertStringContainsString($viewUrl, $html);
        $this->assertStringNotContainsString($editUrl, $html);
    }

    public function test_view_project_page_loads_with_its_relation_manager_tabs(): void
    {
        $project = Project::factory()->create(['created_by_user_id' => auth()->id()]);

        Livewire::test(ViewProject::class, ['record' => $project->getRouteKey()])
            ->assertOk()
            ->assertSee('Fases')
            ->assertSee('Evidencias — entregas y evaluación');
    }

    public function test_publish_action_changes_status_and_is_hidden_once_published(): void
    {
        $project = Project::factory()->draft()->create(['created_by_user_id' => auth()->id()]);

        Livewire::test(ViewProject::class, ['record' => $project->getRouteKey()])
            ->assertActionVisible('publish')
            ->assertActionHidden('unpublish')
            ->callAction('publish');

        $this->assertDatabaseHas('projects', ['id' => $project->id, 'status' => 'published']);
    }

    public function test_unpublish_action_changes_status_back_to_draft(): void
    {
        $project = Project::factory()->create(['created_by_user_id' => auth()->id()]);

        Livewire::test(ViewProject::class, ['record' => $project->getRouteKey()])
            ->assertActionVisible('unpublish')
            ->assertActionHidden('publish')
            ->callAction('unpublish');

        $this->assertDatabaseHas('projects', ['id' => $project->id, 'status' => 'draft']);
    }

    /**
     * "Publicar/despublicar respeta las Policies own/all ya existentes",
     * primera capa: ProjectResource::getEloquentQuery() ya escopea a
     * created_by_user_id cuando el usuario no tiene projects.view.all -- un
     * teacher sin ese permiso ni siquiera puede CARGAR la página de un
     * proyecto ajeno (404 real, más estricto que solo ocultar el botón).
     */
    public function test_teacher_without_view_all_cannot_even_load_someone_elses_project(): void
    {
        $otherTeacher = User::factory()->create()->assignRole('teacher');
        $project = Project::factory()->draft()->create(['created_by_user_id' => $otherTeacher->id]);

        $this->expectException(ModelNotFoundException::class);

        Livewire::test(ViewProject::class, ['record' => $project->getRouteKey()]);
    }

    /**
     * Segunda capa, la que realmente ejercita la visibilidad de la acción:
     * un usuario con projects.view.all (puede cargar CUALQUIER proyecto)
     * pero SIN projects.update.all ni .own -- llega a la página, pero
     * "Publicar" queda oculto porque Gate::allows('update', $record) es
     * false, mismo criterio own/all que ya gobierna EditAction.
     */
    public function test_user_with_view_all_but_no_update_permission_cannot_publish(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('projects.view.all');
        $this->actingAs($viewer);

        $otherTeacher = User::factory()->create()->assignRole('teacher');
        $project = Project::factory()->draft()->create(['created_by_user_id' => $otherTeacher->id]);

        Livewire::test(ViewProject::class, ['record' => $project->getRouteKey()])
            ->assertOk()
            ->assertActionHidden('publish');

        $this->assertDatabaseHas('projects', ['id' => $project->id, 'status' => 'draft']);
    }

    public function test_project_can_be_edited_by_its_creator(): void
    {
        $project = Project::factory()->create(['created_by_user_id' => auth()->id(), 'title' => 'Original']);

        Livewire::test(EditProject::class, ['record' => $project->getRouteKey()])
            ->fillForm(['title' => 'Renombrado'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('projects', ['id' => $project->id, 'title' => 'Renombrado']);
    }
}
