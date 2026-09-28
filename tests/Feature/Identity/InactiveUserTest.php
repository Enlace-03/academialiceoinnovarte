<?php

namespace Tests\Feature\Identity;

use App\Livewire\Shared\Login;
use App\Models\User;
use App\Modules\Identity\Actions\GrantStudentSessionAction;
use App\Modules\Institution\Models\Group;
use Database\Seeders\RoleLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * users.is_active se hace cumplir en cuatro puntos: el login del portal
 * (Login.php), el acceso a los paneles (User::canAccessPanel(), que Filament
 * re-evalúa en cada petición del panel), cualquier sesión ya abierta
 * (EnsureUserIsActive, grupo 'web' -- portal y POST /livewire/update) y la
 * entrega de sesión de estudiante.
 *
 * Los POST a /livewire/update son HTTP reales, no Livewire::test(): el
 * middleware del grupo 'web' solo corre cuando la petición pega de verdad
 * contra el endpoint de Livewire.
 */
class InactiveUserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLevelSeeder::class);
    }

    public function test_an_inactive_user_with_the_correct_password_cannot_log_into_the_portal(): void
    {
        $user = User::factory()->create(['password' => 'secret123', 'is_active' => false])->assignRole('parent');

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'secret123')
            ->call('login')
            ->assertSet('errorMessage', 'Tu cuenta está desactivada. Comunícate con secretaría.')
            ->assertNoRedirect();

        $this->assertGuest();
    }

    public function test_an_inactive_user_cannot_access_the_admin_or_academic_panels(): void
    {
        $secretary = User::factory()->create(['is_active' => false])->assignRole('secretary');
        $teacher = User::factory()->create(['is_active' => false])->assignRole('teacher');

        $this->actingAs($secretary)->get('/admin')->assertForbidden();
        $this->actingAs($teacher)->get('/academia')->assertForbidden();
    }

    public function test_a_portal_user_deactivated_mid_session_is_logged_out_on_the_next_get(): void
    {
        $student = User::factory()->create()->assignRole('student');

        $this->actingAs($student)->get('/mis-proyectos')->assertOk();

        $student->forceFill(['is_active' => false])->save();

        $this->get('/mis-proyectos')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_a_portal_user_deactivated_mid_session_is_logged_out_on_livewire_update(): void
    {
        $student = User::factory()->create()->assignRole('student');
        $this->actingAs($student);

        $snapshot = $this->snapshotFrom('/mis-proyectos', 'my-projects');

        $student->forceFill(['is_active' => false])->save();

        $this->livewireUpdate($snapshot)->assertRedirect(route('login'));
        $this->assertGuest();
    }

    /**
     * Control positivo del payload armado a mano: el mismo POST con el
     * usuario todavía activo debe responder 200. Sin esto, el test de
     * arriba podría pasar por un payload inválido en vez de por el
     * middleware.
     */
    public function test_the_same_livewire_update_succeeds_for_an_active_user(): void
    {
        $student = User::factory()->create()->assignRole('student');
        $this->actingAs($student);

        $snapshot = $this->snapshotFrom('/mis-proyectos', 'my-projects');

        $this->livewireUpdate($snapshot)->assertOk();
        $this->assertAuthenticatedAs($student);
    }

    public function test_a_deactivated_delivered_student_session_is_logged_out_and_the_grant_is_closed(): void
    {
        $group = Group::factory()->create();
        $teacher = User::factory()->create()->assignRole('teacher');
        $student = User::factory()->create(['group_id' => $group->id])->assignRole('student');

        $this->actingAs($teacher);
        $grant = app(GrantStudentSessionAction::class)->execute($teacher, $student, $group, null, null);

        auth()->user()->forceFill(['is_active' => false])->save();

        $this->get('/mis-proyectos')->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertNotNull($grant->fresh()->ended_at);
    }

    /**
     * Dentro de /admin: el GET del panel NO pasa por el grupo 'web' --
     * lo corta Filament (Authenticate -> canAccessPanel() -> 403, sin
     * cerrar la sesión). El POST /livewire/update de un componente del
     * panel SÍ pasa por 'web' -- lo corta EnsureUserIsActive (logout +
     * redirect a login).
     */
    public function test_an_admin_panel_user_deactivated_mid_session_is_cut_off(): void
    {
        $secretary = User::factory()->create()->assignRole('secretary');
        $this->actingAs($secretary);

        $snapshot = $this->snapshotFrom('/admin', 'dashboard');

        $secretary->forceFill(['is_active' => false])->save();

        $this->get('/admin')->assertForbidden();
        $this->assertAuthenticatedAs($secretary);

        $this->livewireUpdate($snapshot)->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_an_academic_panel_user_deactivated_mid_session_is_cut_off(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');
        $this->actingAs($teacher);

        $snapshot = $this->snapshotFrom('/academia', 'dashboard');

        $teacher->forceFill(['is_active' => false])->save();

        $this->get('/academia')->assertForbidden();
        $this->assertAuthenticatedAs($teacher);

        $this->livewireUpdate($snapshot)->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_a_reactivated_user_can_log_in_and_access_their_panel_again(): void
    {
        $teacher = User::factory()->create(['password' => 'secret123', 'is_active' => false])->assignRole('teacher');

        $teacher->forceFill(['is_active' => true])->save();

        Livewire::test(Login::class)
            ->set('email', $teacher->email)
            ->set('password', 'secret123')
            ->call('login')
            ->assertSet('errorMessage', '');

        $this->assertAuthenticatedAs($teacher);
        $this->get('/academia')->assertOk();
    }

    public function test_a_session_cannot_be_delivered_to_an_inactive_student(): void
    {
        $group = Group::factory()->create();
        $teacher = User::factory()->create()->assignRole('teacher');
        $active = User::factory()->create(['group_id' => $group->id, 'name' => 'Estudiante Activo'])->assignRole('student');
        $inactive = User::factory()->create(['group_id' => $group->id, 'name' => 'Estudiante Inactivo', 'is_active' => false])->assignRole('student');

        $this->actingAs($teacher);

        $this->get(route('academic.group-sessions.create', $group))
            ->assertOk()
            ->assertSee($active->name)
            ->assertDontSee($inactive->name);

        $this->post(route('academic.group-sessions.store', $group), ['student_id' => $inactive->id])
            ->assertStatus(422);

        $this->assertAuthenticatedAs($teacher);
    }

    /**
     * La página puede montar varios componentes Livewire -- se elige el
     * snapshot por nombre, no por posición.
     */
    private function snapshotFrom(string $url, string $componentNameFragment): string
    {
        $html = $this->get($url)->assertOk()->getContent();

        preg_match_all('/wire:snapshot="([^"]+)"/', $html, $matches);

        foreach ($matches[1] as $encoded) {
            $snapshot = html_entity_decode($encoded, ENT_QUOTES);

            if (str_contains(json_decode($snapshot, true)['memo']['name'] ?? '', $componentNameFragment)) {
                return $snapshot;
            }
        }

        $this->fail("No se encontró un componente '{$componentNameFragment}' en {$url}.");
    }

    private function livewireUpdate(string $snapshot)
    {
        return $this->withHeaders(['X-Livewire' => 'true'])->postJson('/livewire/update', [
            'components' => [[
                'snapshot' => $snapshot,
                // Ida y vuelta sin cambios ni llamadas: el re-render más
                // simple que acepta cualquier componente.
                'updates' => [],
                'calls' => [],
            ]],
        ]);
    }
}
