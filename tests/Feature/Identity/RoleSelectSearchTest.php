<?php

namespace Tests\Feature\Identity;

use App\Filament\Admin\Resources\Users\Pages\CreateUser;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Regression for a bug where the Roles select searched only the raw Spatie
 * role name ('teacher'), not the Spanish label shown to the user ('Docente')
 * — because Select::relationship() defaults its search column to the
 * relationship's titleAttribute, ignoring getOptionLabelFromRecordUsing().
 */
class RoleSelectSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $admin = User::factory()->create()->assignRole('super_admin');
        $this->actingAs($admin);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    protected function rolesField(): Select
    {
        $response = Livewire::test(CreateUser::class);

        /** @var Select $field */
        $field = $response->instance()->form->getFlatFields(withHidden: true)['roles'];

        return $field;
    }

    protected function roleId(string $name): int
    {
        return Role::where('name', $name)->value('id');
    }

    public function test_searching_roles_by_spanish_label_finds_the_matching_role(): void
    {
        $results = $this->rolesField()->getSearchResultsForJs('Docente');

        $this->assertContains(
            ['label' => 'Docente', 'value' => (string) $this->roleId('teacher'), 'isDisabled' => false],
            $results,
        );
    }

    public function test_searching_roles_by_raw_spatie_name_still_works(): void
    {
        $results = $this->rolesField()->getSearchResultsForJs('teacher');

        $this->assertContains(
            ['label' => 'Docente', 'value' => (string) $this->roleId('teacher'), 'isDisabled' => false],
            $results,
        );
    }

    public function test_searching_roles_is_case_insensitive_and_partial(): void
    {
        $results = $this->rolesField()->getSearchResultsForJs('rec');

        $this->assertContains(
            ['label' => 'Rector', 'value' => (string) $this->roleId('rector'), 'isDisabled' => false],
            $results,
        );
    }

    public function test_searching_roles_excludes_non_matching_roles(): void
    {
        $results = $this->rolesField()->getSearchResultsForJs('Docente');

        $this->assertNotContains('Rector', array_column($results, 'label'));
    }
}
