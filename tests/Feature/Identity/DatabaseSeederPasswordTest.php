<?php

namespace Tests\Feature\Identity;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

/**
 * DatabaseSeeder ya no cae en un valor por defecto conocido para el super
 * admin: sin SEED_SUPER_ADMIN_PASSWORD falla con un mensaje claro, antes de
 * sembrar nada.
 */
class DatabaseSeederPasswordTest extends TestCase
{
    use RefreshDatabase;

    private ?string $originalPassword;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalPassword = env('SEED_SUPER_ADMIN_PASSWORD');
    }

    protected function tearDown(): void
    {
        // Restaura el valor de .env.testing para no afectar a otros tests.
        if ($this->originalPassword === null) {
            Env::getRepository()->clear('SEED_SUPER_ADMIN_PASSWORD');
        } else {
            Env::getRepository()->set('SEED_SUPER_ADMIN_PASSWORD', $this->originalPassword);
        }

        parent::tearDown();
    }

    public function test_running_the_seeder_without_the_variable_fails_with_a_clear_message(): void
    {
        Env::getRepository()->clear('SEED_SUPER_ADMIN_PASSWORD');
        $this->assertNull(env('SEED_SUPER_ADMIN_PASSWORD'));

        try {
            $this->seed(DatabaseSeeder::class);
            $this->fail('El seeder debió lanzar una excepción sin SEED_SUPER_ADMIN_PASSWORD.');
        } catch (RuntimeException $e) {
            $this->assertSame('Define SEED_SUPER_ADMIN_PASSWORD antes de correr este seeder', $e->getMessage());
        }

        // Falla antes de sembrar nada, no deja la base a medias.
        $this->assertSame(0, User::where('email', 'diego@admin.edu.co')->count());
    }

    public function test_an_empty_value_is_treated_as_not_defined(): void
    {
        Env::getRepository()->set('SEED_SUPER_ADMIN_PASSWORD', '');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Define SEED_SUPER_ADMIN_PASSWORD antes de correr este seeder');

        $this->seed(DatabaseSeeder::class);
    }

    public function test_with_the_variable_defined_the_seeder_works_as_before(): void
    {
        Env::getRepository()->set('SEED_SUPER_ADMIN_PASSWORD', 'clave-de-prueba-123');

        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'diego@admin.edu.co')->firstOrFail();

        $this->assertTrue($admin->hasRole('super_admin'));
        $this->assertTrue(Hash::check('clave-de-prueba-123', $admin->password));
    }
}
