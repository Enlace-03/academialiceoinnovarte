<?php

namespace Tests\Feature\Identity;

use App\Filament\Admin\Resources\Users\Pages\CreateUser;
use App\Models\User;
use Database\Seeders\RoleLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ExclusiveIdentityRoleTest extends TestCase
{
    use RefreshDatabase;

    protected const EXCLUSIVITY_MESSAGE = 'Un usuario con rol de Estudiante o Acudiente no puede tener ningún otro rol asignado.';

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLevelSeeder::class);

        $this->admin = User::factory()->create()->assignRole('super_admin');
        $this->actingAs($this->admin);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    protected function roleId(string $name): int
    {
        return Role::where('name', $name)->value('id');
    }

    protected function baseFormData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Usuario Prueba',
            'email' => 'usuario.prueba@test.com',
            'password' => 'secret123',
        ], $overrides);
    }

    public function test_student_role_cannot_be_combined_with_a_staff_role(): void
    {
        Livewire::test(CreateUser::class)
            ->fillForm($this->baseFormData([
                'roles' => [$this->roleId('student'), $this->roleId('teacher')],
            ]))
            ->call('create')
            ->assertHasFormErrors(['roles' => self::EXCLUSIVITY_MESSAGE]);

        $this->assertDatabaseMissing('users', ['email' => 'usuario.prueba@test.com']);
    }

    public function test_student_role_alone_passes(): void
    {
        Livewire::test(CreateUser::class)
            ->fillForm($this->baseFormData([
                'roles' => [$this->roleId('student')],
            ]))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', ['email' => 'usuario.prueba@test.com']);
    }

    public function test_parent_role_cannot_be_combined_with_a_staff_role(): void
    {
        Livewire::test(CreateUser::class)
            ->fillForm($this->baseFormData([
                'roles' => [$this->roleId('parent'), $this->roleId('teacher')],
            ]))
            ->call('create')
            ->assertHasFormErrors(['roles' => self::EXCLUSIVITY_MESSAGE]);

        $this->assertDatabaseMissing('users', ['email' => 'usuario.prueba@test.com']);
    }

    public function test_parent_role_alone_passes(): void
    {
        Livewire::test(CreateUser::class)
            ->fillForm($this->baseFormData([
                'roles' => [$this->roleId('parent')],
            ]))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', ['email' => 'usuario.prueba@test.com']);
    }

    public function test_student_and_parent_together_fails(): void
    {
        Livewire::test(CreateUser::class)
            ->fillForm($this->baseFormData([
                'roles' => [$this->roleId('student'), $this->roleId('parent')],
            ]))
            ->call('create')
            ->assertHasFormErrors(['roles' => self::EXCLUSIVITY_MESSAGE]);

        $this->assertDatabaseMissing('users', ['email' => 'usuario.prueba@test.com']);
    }

    public function test_two_staff_roles_can_still_be_combined(): void
    {
        Livewire::test(CreateUser::class)
            ->fillForm($this->baseFormData([
                'roles' => [$this->roleId('rector'), $this->roleId('teacher')],
            ]))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', ['email' => 'usuario.prueba@test.com']);
    }
}
