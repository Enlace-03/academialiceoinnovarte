<?php

namespace Tests\Feature\Auth;

use App\Livewire\Shared\Login;
use App\Models\User;
use Database\Seeders\RoleLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Límite de intentos del login del portal (estudiante/acudiente): 5 fallos
 * por 60 segundos por pareja correo+IP. Los paneles Filament (/admin,
 * /academia) ya traen su propio límite en Filament\Auth\Pages\Login.
 */
class PortalLoginRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLevelSeeder::class);
    }

    public function test_the_sixth_attempt_is_blocked_even_with_the_correct_password(): void
    {
        $user = User::factory()->create(['password' => 'secret123'])->assignRole('student');

        $this->failLogin($user->email, 5);

        $component = $this->attemptLogin($user->email, 'secret123');

        $this->assertStringStartsWith('Demasiados intentos. Intenta de nuevo en ', $component->get('errorMessage'));
        $this->assertStringEndsWith(' segundos.', $component->get('errorMessage'));
        $this->assertGuest();
    }

    public function test_a_different_email_from_the_same_ip_is_not_blocked(): void
    {
        $blocked = User::factory()->create(['password' => 'secret123'])->assignRole('student');
        $other = User::factory()->create(['password' => 'secret456'])->assignRole('student');

        $this->failLogin($blocked->email, 5);

        $this->attemptLogin($other->email, 'secret456');

        $this->assertAuthenticatedAs($other);
    }

    public function test_the_email_is_case_insensitive_for_the_limit(): void
    {
        $user = User::factory()->create(['email' => 'estudiante@example.com', 'password' => 'secret123'])->assignRole('student');

        $this->failLogin('ESTUDIANTE@example.com', 5);

        $component = $this->attemptLogin($user->email, 'secret123');

        $this->assertStringStartsWith('Demasiados intentos.', $component->get('errorMessage'));
        $this->assertGuest();
    }

    public function test_a_successful_login_clears_the_failed_attempts_counter(): void
    {
        $user = User::factory()->create(['password' => 'secret123'])->assignRole('student');

        $this->failLogin($user->email, 4);
        $this->attemptLogin($user->email, 'secret123');
        $this->assertAuthenticatedAs($user);

        auth()->logout();

        // Si el contador no se hubiera limpiado, 4 + 1 = 5 fallos bloquearían
        // el siguiente intento correcto.
        $this->failLogin($user->email, 1);
        $this->attemptLogin($user->email, 'secret123');

        $this->assertAuthenticatedAs($user);
    }

    private function failLogin(string $email, int $times): void
    {
        for ($i = 0; $i < $times; $i++) {
            $this->attemptLogin($email, 'wrong-password')
                ->assertSet('errorMessage', 'Estas credenciales no coinciden con nuestros registros.');
        }
    }

    private function attemptLogin(string $email, string $password)
    {
        return Livewire::test(Login::class)
            ->set('email', $email)
            ->set('password', $password)
            ->call('login');
    }
}
