<?php

namespace Tests\Feature\Identity;

use App\Models\User;
use Database\Seeders\RoleLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserCanAccessPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLevelSeeder::class);
    }

    protected function panel(string $id): Panel
    {
        return Panel::make()->id($id);
    }

    public function test_super_admin_can_access_admin_panel(): void
    {
        $user = User::factory()->create()->assignRole('super_admin');

        $this->assertTrue($user->canAccessPanel($this->panel('admin')));
    }

    public function test_super_admin_can_access_academic_panel(): void
    {
        $user = User::factory()->create()->assignRole('super_admin');

        $this->assertTrue($user->canAccessPanel($this->panel('academic')));
    }

    public function test_rector_can_access_admin_panel(): void
    {
        $user = User::factory()->create()->assignRole('rector');

        $this->assertTrue($user->canAccessPanel($this->panel('admin')));
    }

    public function test_coordinator_can_access_admin_panel(): void
    {
        $user = User::factory()->create()->assignRole('coordinator');

        $this->assertTrue($user->canAccessPanel($this->panel('admin')));
    }

    public function test_secretary_can_access_admin_panel(): void
    {
        $user = User::factory()->create()->assignRole('secretary');

        $this->assertTrue($user->canAccessPanel($this->panel('admin')));
    }

    public function test_teacher_cannot_access_admin_panel(): void
    {
        $user = User::factory()->create()->assignRole('teacher');

        $this->assertFalse($user->canAccessPanel($this->panel('admin')));
    }

    public function test_student_cannot_access_admin_panel(): void
    {
        $user = User::factory()->create()->assignRole('student');

        $this->assertFalse($user->canAccessPanel($this->panel('admin')));
    }

    public function test_parent_cannot_access_admin_panel(): void
    {
        $user = User::factory()->create()->assignRole('parent');

        $this->assertFalse($user->canAccessPanel($this->panel('admin')));
    }

    public function test_teacher_can_access_academic_panel(): void
    {
        $user = User::factory()->create()->assignRole('teacher');

        $this->assertTrue($user->canAccessPanel($this->panel('academic')));
    }

    public function test_student_can_access_academic_panel(): void
    {
        $user = User::factory()->create()->assignRole('student');

        $this->assertTrue($user->canAccessPanel($this->panel('academic')));
    }

    public function test_parent_can_access_academic_panel(): void
    {
        $user = User::factory()->create()->assignRole('parent');

        $this->assertTrue($user->canAccessPanel($this->panel('academic')));
    }

    public function test_user_with_individual_users_permission_but_no_staff_role_can_access_admin_panel(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('users.view');

        $this->assertTrue($user->canAccessPanel($this->panel('admin')));
    }

    public function test_user_with_no_role_cannot_access_admin_panel(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->canAccessPanel($this->panel('admin')));
    }

    public function test_user_with_no_role_cannot_access_academic_panel(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->canAccessPanel($this->panel('academic')));
    }

    public function test_unknown_panel_is_denied_by_default(): void
    {
        $user = User::factory()->create()->assignRole('super_admin');

        $this->assertFalse($user->canAccessPanel($this->panel('some-other-panel')));
    }
}
