<?php

namespace Tests\Feature\Assessment;

use App\Models\User;
use App\Modules\Assessment\Models\Observation;
use Database\Seeders\RoleLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ObservationPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLevelSeeder::class);
    }

    public function test_teacher_can_manage_their_own_observation_but_not_someone_elses(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');
        $otherTeacher = User::factory()->create()->assignRole('teacher');

        $own = Observation::factory()->create(['teacher_id' => $teacher->id]);
        $other = Observation::factory()->create(['teacher_id' => $otherTeacher->id]);

        $this->assertTrue($teacher->can('update', $own));
        $this->assertFalse($teacher->can('update', $other));
    }

    public function test_rector_can_manage_any_observation_via_write_all(): void
    {
        $rector = User::factory()->create()->assignRole('rector');
        $teacher = User::factory()->create()->assignRole('teacher');
        $observation = Observation::factory()->create(['teacher_id' => $teacher->id]);

        $this->assertTrue($rector->hasPermissionTo('observations.write.all'));
        $this->assertTrue($rector->can('update', $observation));
        $this->assertTrue($rector->can('view', $observation));
    }

    /**
     * coordinator conserva observations.view.all sin cambios (solo lectura)
     * — decisión explícita: no se le dio capacidad de escritura en este hito.
     */
    public function test_coordinator_can_view_but_not_write_any_observation(): void
    {
        $coordinator = User::factory()->create()->assignRole('coordinator');
        $teacher = User::factory()->create()->assignRole('teacher');
        $observation = Observation::factory()->create(['teacher_id' => $teacher->id]);

        $this->assertTrue($coordinator->can('view', $observation));
        $this->assertFalse($coordinator->can('create'));
        $this->assertFalse($coordinator->can('update', $observation));
    }

    public function test_teacher_without_any_observation_permission_cannot_create(): void
    {
        $secretary = User::factory()->create()->assignRole('secretary');

        $this->assertFalse($secretary->can('create', Observation::class));
    }
}
