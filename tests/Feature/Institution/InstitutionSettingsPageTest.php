<?php

namespace Tests\Feature\Institution;

use App\Filament\Admin\Pages\InstitutionSettingsPage;
use App\Models\User;
use App\Modules\Institution\Models\Institution;
use App\Modules\Institution\Models\InstitutionSetting;
use Database\Seeders\RoleLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InstitutionSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLevelSeeder::class);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_saving_the_form_updates_the_value_and_invalidates_the_cache(): void
    {
        $institution = Institution::factory()->create();

        $user = User::factory()->create();
        $user->givePermissionTo('institution.settings.manage');
        $this->actingAs($user);

        // Se lee (y cachea) el valor por defecto antes de guardar nada.
        $this->assertSame(
            (string) config('school.current_academic_year'),
            (string) InstitutionSetting::get('current_academic_year', config('school.current_academic_year'))
        );

        Livewire::test(InstitutionSettingsPage::class)
            ->fillForm(['current_academic_year' => 2027])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('institution_settings', [
            'institution_id' => $institution->id,
            'key' => 'current_academic_year',
            'value' => '2027',
        ]);

        // Si el caché no se hubiera invalidado al guardar, esto seguiría
        // devolviendo el valor viejo cacheado en la aserción de arriba.
        $this->assertSame('2027', InstitutionSetting::get('current_academic_year'));
    }

    public function test_reopening_the_page_shows_the_previously_saved_value(): void
    {
        $institution = Institution::factory()->create();
        InstitutionSetting::factory()->for($institution)->create([
            'key' => 'current_academic_year',
            'value' => '2027',
        ]);

        $user = User::factory()->create();
        $user->givePermissionTo('institution.settings.manage');
        $this->actingAs($user);

        Livewire::test(InstitutionSettingsPage::class)
            ->assertFormSet(['current_academic_year' => '2027']);
    }

    public function test_a_user_without_the_permission_cannot_access_the_page(): void
    {
        Institution::factory()->create();

        // Tiene un permiso institution.* distinto, así que sí entra al panel
        // /admin, pero no debe poder ver esta página en particular.
        $user = User::factory()->create();
        $user->givePermissionTo('institution.manage');

        $this->actingAs($user)
            ->get(route('filament.admin.pages.configuracion-institucional'))
            ->assertForbidden();
    }

    public function test_a_user_with_the_permission_can_access_the_page(): void
    {
        Institution::factory()->create();

        $user = User::factory()->create();
        $user->givePermissionTo('institution.settings.manage');

        $this->actingAs($user)
            ->get(route('filament.admin.pages.configuracion-institucional'))
            ->assertSuccessful();
    }
}
