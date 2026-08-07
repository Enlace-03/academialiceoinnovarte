<?php

namespace Tests\Feature\Assessment;

use App\Models\User;
use App\Modules\Assessment\Models\Rubric;
use Database\Seeders\RoleLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RubricPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLevelSeeder::class);
    }

    public function test_teacher_coordinator_and_rector_can_manage_any_rubric(): void
    {
        $rubric = Rubric::factory()->create();

        foreach (['teacher', 'coordinator', 'rector'] as $role) {
            $user = User::factory()->create()->assignRole($role);

            $this->assertTrue($user->can('create', Rubric::class), "{$role} debería poder crear rúbricas");
            $this->assertTrue($user->can('update', $rubric), "{$role} debería poder editar cualquier rúbrica");
        }
    }

    public function test_secretary_cannot_manage_rubrics(): void
    {
        $secretary = User::factory()->create()->assignRole('secretary');
        $rubric = Rubric::factory()->create();

        $this->assertFalse($secretary->can('create', Rubric::class));
        $this->assertFalse($secretary->can('update', $rubric));
    }
}
