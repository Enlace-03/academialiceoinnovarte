<?php

namespace Tests\Feature\Project;

use App\Filament\Academic\Resources\Projects\Pages\CreateProject;
use App\Filament\Academic\Resources\Projects\Pages\EditProject;
use App\Filament\Academic\Resources\Projects\Pages\ListProjects;
use App\Models\User;
use App\Modules\Institution\Models\Cycle;
use App\Modules\Project\Models\Project;
use Database\Seeders\RoleLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
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
