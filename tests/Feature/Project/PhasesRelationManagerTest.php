<?php

namespace Tests\Feature\Project;

use App\Filament\Academic\Resources\Projects\Pages\EditProject;
use App\Filament\Academic\Resources\Projects\RelationManagers\PhasesRelationManager;
use App\Models\User;
use App\Modules\Project\Models\Project;
use Database\Seeders\RoleLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * No hay navegador disponible en este entorno para la verificación visual
 * pedida (crear un proyecto, ver las 4 fases, agregar guía + evidencia).
 * Este test ejercita el mismo camino real (el RelationManager de Filament,
 * no una llamada directa al modelo) como sustituto funcional.
 */
class PhasesRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_add_a_guide_and_an_expected_evidence_to_a_phase(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLevelSeeder::class);

        $teacher = User::factory()->create()->assignRole('teacher');
        $this->actingAs($teacher);
        Filament::setCurrentPanel(Filament::getPanel('academic'));

        $project = Project::factory()->create(['created_by_user_id' => $teacher->id]);
        $phase = $project->phases()->orderBy('order')->first();

        Livewire::test(PhasesRelationManager::class, [
            'ownerRecord' => $project,
            'pageClass' => EditProject::class,
        ])
            ->mountTableAction('edit', $phase)
            ->setTableActionData([
                'name' => $phase->name,
                'description' => $phase->description,
                'guides' => [
                    ['title' => 'Guía: el agua que compartimos', 'content' => 'Contenido base de la guía.'],
                ],
                'resources' => [],
                'expectedEvidences' => [
                    [
                        'type' => 'archivo',
                        'description' => 'Afiche informativo sobre el consumo responsable del agua.',
                        'is_required' => true,
                        'alternative_group' => null,
                    ],
                ],
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('guides', [
            'phase_id' => $phase->id,
            'title' => 'Guía: el agua que compartimos',
        ]);

        $this->assertDatabaseHas('expected_evidences', [
            'phase_id' => $phase->id,
            'type' => 'archivo',
            'is_required' => 1,
        ]);
    }
}
