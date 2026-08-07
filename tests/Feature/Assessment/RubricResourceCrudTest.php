<?php

namespace Tests\Feature\Assessment;

use App\Filament\Academic\Resources\Rubrics\Pages\CreateRubric;
use App\Filament\Academic\Resources\Rubrics\Pages\ListRubrics;
use App\Models\User;
use App\Modules\Assessment\Models\Rubric;
use App\Modules\Institution\Models\Institution;
use Database\Seeders\RoleLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RubricResourceCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLevelSeeder::class);
        Institution::factory()->create();

        $this->actingAs(User::factory()->create()->assignRole('teacher'));
        Filament::setCurrentPanel(Filament::getPanel('academic'));
    }

    public function test_rubrics_list_loads(): void
    {
        Rubric::factory()->create();

        Livewire::test(ListRubrics::class)->assertOk();
    }

    public function test_creating_a_rubric_with_nested_criteria(): void
    {
        Livewire::test(CreateRubric::class)
            ->fillForm([
                'name' => 'Rúbrica de campaña comunicativa',
                'description' => 'Evalúa la campaña final del proyecto del agua.',
                'criteria' => [
                    [
                        'name' => 'Claridad del mensaje',
                        'level_descriptions' => [
                            'inicio' => 'El mensaje no es claro.',
                            'en_proceso' => 'El mensaje es parcialmente claro.',
                            'logro_esperado' => 'El mensaje es claro.',
                            'logro_destacado' => 'El mensaje es claro y memorable.',
                        ],
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $rubric = Rubric::where('name', 'Rúbrica de campaña comunicativa')->first();

        $this->assertNotNull($rubric);
        $this->assertCount(1, $rubric->criteria);
        $this->assertSame('El mensaje es claro.', $rubric->criteria->first()->level_descriptions['logro_esperado']);
    }
}
