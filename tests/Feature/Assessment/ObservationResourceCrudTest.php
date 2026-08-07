<?php

namespace Tests\Feature\Assessment;

use App\Filament\Academic\Resources\Observations\Pages\CreateObservation;
use App\Filament\Academic\Resources\Observations\Pages\ListObservations;
use App\Models\User;
use App\Modules\Assessment\Models\Observation;
use Database\Seeders\RoleLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ObservationResourceCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLevelSeeder::class);
    }

    public function test_observations_list_loads(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');
        $this->actingAs($teacher);
        Filament::setCurrentPanel(Filament::getPanel('academic'));

        Observation::factory()->create(['teacher_id' => $teacher->id]);

        Livewire::test(ListObservations::class)->assertOk();
    }

    public function test_creating_an_observation_sets_teacher_id_automatically(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');
        $student = User::factory()->create()->assignRole('student');
        $this->actingAs($teacher);
        Filament::setCurrentPanel(Filament::getPanel('academic'));

        Livewire::test(CreateObservation::class)
            ->fillForm([
                'student_id' => $student->id,
                'content' => 'Mostró mucho interés investigando el problema del agua.',
                'visible_to_parents' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $observation = Observation::where('student_id', $student->id)->first();

        $this->assertNotNull($observation);
        $this->assertSame($teacher->id, $observation->teacher_id);
    }
}
