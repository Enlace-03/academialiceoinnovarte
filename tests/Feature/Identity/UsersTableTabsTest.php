<?php

namespace Tests\Feature\Identity;

use App\Filament\Admin\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UsersTableTabsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $rector;

    protected User $student;

    protected User $guardian;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create()->assignRole('super_admin');
        $this->rector = User::factory()->create()->assignRole('rector');
        $this->student = User::factory()->create()->assignRole('student');
        $this->guardian = User::factory()->create()->assignRole('parent');

        $this->actingAs($this->admin);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_personal_is_the_default_tab_and_shows_only_staff_users(): void
    {
        Livewire::test(ListUsers::class)
            ->assertSet('activeTab', 'staff')
            ->assertCanSeeTableRecords([$this->admin, $this->rector])
            ->assertCanNotSeeTableRecords([$this->student, $this->guardian])
            ->assertCountTableRecords(2);
    }

    public function test_estudiantes_tab_shows_only_student_users(): void
    {
        Livewire::test(ListUsers::class)
            ->set('activeTab', 'students')
            ->assertCanSeeTableRecords([$this->student])
            ->assertCanNotSeeTableRecords([$this->admin, $this->rector, $this->guardian])
            ->assertCountTableRecords(1);
    }

    public function test_acudientes_tab_shows_only_parent_users(): void
    {
        Livewire::test(ListUsers::class)
            ->set('activeTab', 'guardians')
            ->assertCanSeeTableRecords([$this->guardian])
            ->assertCanNotSeeTableRecords([$this->admin, $this->rector, $this->student])
            ->assertCountTableRecords(1);
    }

    public function test_tab_badges_reflect_the_correct_counts(): void
    {
        $tabs = Livewire::test(ListUsers::class)->instance()->getTabs();

        $this->assertSame('2', $tabs['staff']->getBadge());
        $this->assertSame('1', $tabs['students']->getBadge());
        $this->assertSame('1', $tabs['guardians']->getBadge());
    }
}
