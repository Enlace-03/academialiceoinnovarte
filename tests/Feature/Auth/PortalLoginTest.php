<?php

namespace Tests\Feature\Auth;

use App\Livewire\Shared\Login;
use App\Models\User;
use Database\Seeders\RoleLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cookie;
use Livewire\Livewire;
use Tests\TestCase;

class PortalLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLevelSeeder::class);
    }

    public function test_guest_is_redirected_from_home_to_login(): void
    {
        $this->get(route('portal.home'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_is_redirected_away_from_login(): void
    {
        $user = User::factory()->create()->assignRole('student');
        $this->actingAs($user);

        $this->get(route('login'))->assertRedirect();
    }

    public function test_valid_credentials_log_the_user_in(): void
    {
        $user = User::factory()->create(['password' => 'secret123'])->assignRole('student');

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'secret123')
            ->call('login');

        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_credentials_show_an_error_and_do_not_log_in(): void
    {
        $user = User::factory()->create(['password' => 'secret123'])->assignRole('student');

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'wrong-password')
            ->call('login')
            ->assertSet('errorMessage', 'Estas credenciales no coinciden con nuestros registros.');

        $this->assertGuest();
    }

    public function test_parent_login_sends_a_remember_cookie(): void
    {
        $parent = User::factory()->create(['password' => 'secret123'])->assignRole('parent');
        $recaller = auth()->guard('web')->getRecallerName();

        Livewire::test(Login::class)
            ->set('email', $parent->email)
            ->set('password', 'secret123')
            ->call('login');

        $this->assertTrue(Cookie::hasQueued($recaller));
    }

    public function test_student_login_does_not_send_a_remember_cookie(): void
    {
        $student = User::factory()->create(['password' => 'secret123'])->assignRole('student');
        $recaller = auth()->guard('web')->getRecallerName();

        Livewire::test(Login::class)
            ->set('email', $student->email)
            ->set('password', 'secret123')
            ->call('login');

        $this->assertFalse(Cookie::hasQueued($recaller));
    }

    public function test_parent_remember_cookie_expires_in_about_90_days_not_the_laravel_default(): void
    {
        $parent = User::factory()->create(['password' => 'secret123'])->assignRole('parent');
        $recaller = auth()->guard('web')->getRecallerName();

        Livewire::test(Login::class)
            ->set('email', $parent->email)
            ->set('password', 'secret123')
            ->call('login');

        $expiresAt = Cookie::queued($recaller)->getExpiresTime();
        $expectedExpiresAt = now()->addDays(90)->getTimestamp();

        // Tolerancia de 1 minuto por el tiempo real de ejecución del test.
        $this->assertEqualsWithDelta($expectedExpiresAt, $expiresAt, 60);
    }

    /**
     * Segunda vuelta del Hito 5: antes de este fix, Login::login() siempre
     * redirigía a portal.home ignorando session('url.intended') -- un clic
     * en el enlace de una notificación sin sesión activa perdía el destino
     * real después de autenticarse. Laravel guarda url.intended solo en
     * peticiones GET normales (Redirector::guest()), no en llamadas
     * Livewire -- por eso esta prueba visita la ruta protegida con $this->get()
     * primero (dispara el middleware real), y solo después ejercita el
     * componente Login, verificando que ambas comparten la misma sesión.
     */
    public function test_login_redirects_to_the_originally_intended_url_not_the_generic_home(): void
    {
        $cycle = \App\Modules\Institution\Models\Cycle::factory()->create();
        $grade = \App\Modules\Institution\Models\SchoolGrade::factory()->create(['cycle_id' => $cycle->id]);
        $user = User::factory()->create(['password' => 'secret123', 'school_grade_id' => $grade->id])->assignRole('student');
        $project = \App\Modules\Project\Models\Project::factory()->create(['cycle_id' => $cycle->id]);
        $intendedUrl = route('student.projects.show', $project->uuid);

        $this->get($intendedUrl)->assertRedirect(route('login'));
        $this->assertSame($intendedUrl, session('url.intended'));

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'secret123')
            ->call('login')
            ->assertRedirect($intendedUrl);
    }

    public function test_login_falls_back_to_portal_home_when_there_is_no_intended_url(): void
    {
        $user = User::factory()->create(['password' => 'secret123'])->assignRole('student');

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'secret123')
            ->call('login')
            ->assertRedirect(route('portal.home'));
    }

    public function test_logout_ends_the_session(): void
    {
        $user = User::factory()->create()->assignRole('student');
        $this->actingAs($user);

        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
