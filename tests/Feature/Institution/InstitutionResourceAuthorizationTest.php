<?php

namespace Tests\Feature\Institution;

use App\Models\User;
use Database\Seeders\RoleLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InstitutionResourceAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLevelSeeder::class);
    }

    public static function resourceIndexRoutes(): array
    {
        return [
            'cycles' => ['filament.admin.resources.cycles.index', 'institution.cycles.manage'],
            'thinking-fields' => ['filament.admin.resources.thinking-fields.index', 'institution.thinking-fields.manage'],
            'school-grades' => ['filament.admin.resources.school-grades.index', 'institution.school-grades.manage'],
            'groups' => ['filament.admin.resources.groups.index', 'institution.groups.manage'],
        ];
    }

    #[DataProvider('resourceIndexRoutes')]
    public function test_user_without_the_matching_permission_is_forbidden(string $routeName, string $permission): void
    {
        // Tiene un permiso institution.* distinto, así que sí entra al panel
        // /admin, pero no debe poder ver este Resource en particular.
        $user = User::factory()->create();
        $user->givePermissionTo('institution.manage');

        $this->actingAs($user)
            ->get(route($routeName))
            ->assertForbidden();
    }

    #[DataProvider('resourceIndexRoutes')]
    public function test_user_with_the_matching_permission_can_view_the_resource(string $routeName, string $permission): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permission);

        $this->actingAs($user)
            ->get(route($routeName))
            ->assertOk();
    }

    public function test_rector_preset_can_access_all_four_institution_resources(): void
    {
        $rector = User::factory()->create()->assignRole('rector');

        foreach (self::resourceIndexRoutes() as [$routeName, $permission]) {
            $this->actingAs($rector)
                ->get(route($routeName))
                ->assertOk();
        }
    }

    public function test_user_with_no_permissions_at_all_cannot_reach_admin_panel(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->canAccessPanel(\Filament\Facades\Filament::getPanel('admin')));
    }
}
