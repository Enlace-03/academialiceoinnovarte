<?php

namespace Tests\Feature\Student;

use App\Models\User;
use App\Modules\Identity\Actions\GrantStudentSessionAction;
use App\Modules\Institution\Models\Group;
use Database\Seeders\RoleLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cierre automático de seguridad de una sesión entregada (Hito 3b-2):
 * App\Http\Middleware\ExpireDeliveredStudentSession, verificación activa
 * basada en 'active_grant_last_seen_at' de la sesión, no en SESSION_LIFETIME
 * global (eso afectaría también a personal en /admin y /academia).
 */
class DeliveredSessionExpirationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLevelSeeder::class);
    }

    public function test_an_idle_delivered_session_expires_and_closes_the_grant(): void
    {
        $group = Group::factory()->create();
        $teacher = User::factory()->create()->assignRole('teacher');
        $student = User::factory()->create(['group_id' => $group->id])->assignRole('student');

        $this->actingAs($teacher);
        $grant = app(GrantStudentSessionAction::class)->execute($teacher, $student, $group, null, null);

        // Simula 51 minutos sin actividad -- por encima del límite (50).
        session(['active_grant_last_seen_at' => now()->subMinutes(51)->toISOString()]);

        $response = $this->get('/mis-proyectos');

        $response->assertRedirect(route('login'));
        $this->assertNotNull($grant->fresh()->ended_at);
        $this->assertGuest();
    }

    public function test_a_delivered_session_within_the_idle_window_stays_active(): void
    {
        $group = Group::factory()->create();
        $teacher = User::factory()->create()->assignRole('teacher');
        $student = User::factory()->create(['group_id' => $group->id])->assignRole('student');

        $this->actingAs($teacher);
        $grant = app(GrantStudentSessionAction::class)->execute($teacher, $student, $group, null, null);

        session(['active_grant_last_seen_at' => now()->subMinutes(10)->toISOString()]);

        $response = $this->get('/mis-proyectos');

        $response->assertOk();
        $this->assertNull($grant->fresh()->ended_at);
        $this->assertAuthenticatedAs($student);
    }

    public function test_a_normal_student_login_is_never_affected_by_the_expiration_middleware(): void
    {
        $student = User::factory()->create()->assignRole('student');

        $this->actingAs($student)->get('/mis-proyectos')->assertOk();
    }
}
