<?php

namespace Tests\Feature\Identity;

use App\Livewire\Shared\Login;
use App\Models\User;
use App\Modules\Identity\Actions\EndStudentSessionAction;
use App\Modules\Identity\Actions\GrantStudentSessionAction;
use App\Modules\Identity\Models\StudentSessionGrant;
use App\Modules\Institution\Models\Group;
use Database\Seeders\RoleLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Hito 3b-2: entrega de sesión docente->estudiante vía auth-switch real
 * (Auth::loginUsingId), decisión confirmada en la auditoría previa -- el
 * docente queda deslogueado de /academia en el mismo dispositivo a
 * propósito, no es un efecto colateral a evitar.
 */
class StudentSessionGrantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLevelSeeder::class);
    }

    public function test_granting_a_session_creates_the_record_and_switches_auth_to_the_student(): void
    {
        $group = Group::factory()->create();
        $teacher = User::factory()->create()->assignRole('teacher');
        $student = User::factory()->create(['group_id' => $group->id])->assignRole('student');

        $this->actingAs($teacher);

        $grant = app(GrantStudentSessionAction::class)->execute(
            $teacher, $student, $group, '127.0.0.1', 'PHPUnit-Diagnostic'
        );

        $this->assertDatabaseHas('student_session_grants', [
            'id' => $grant->id,
            'student_id' => $student->id,
            'granted_by_user_id' => $teacher->id,
            'group_id' => $group->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit-Diagnostic',
            'ended_at' => null,
        ]);
        $this->assertNotNull($grant->started_at);
        $this->assertAuthenticatedAs($student);
    }

    public function test_granting_a_session_to_a_student_outside_the_group_is_rejected(): void
    {
        $group = Group::factory()->create();
        $otherGroup = Group::factory()->create();
        $teacher = User::factory()->create()->assignRole('teacher');
        $student = User::factory()->create(['group_id' => $otherGroup->id])->assignRole('student');

        $this->actingAs($teacher);

        $this->expectException(ValidationException::class);

        app(GrantStudentSessionAction::class)->execute($teacher, $student, $group, null, null);
    }

    /**
     * Mismo comportamiento verificado empíricamente en la auditoría previa
     * al Hito 3b-2 (test desechable, ya borrado): auth-switch real
     * sobrescribe la sesión -- no coexisten dos identidades en la misma
     * sesión/pestaña.
     */
    public function test_after_granting_the_teachers_academic_session_is_no_longer_valid_in_this_context(): void
    {
        $group = Group::factory()->create();
        $teacher = User::factory()->create(['password' => 'secret123'])->assignRole('teacher');
        $student = User::factory()->create(['group_id' => $group->id])->assignRole('student');

        Livewire::test(Login::class)
            ->set('email', $teacher->email)
            ->set('password', 'secret123')
            ->call('login');

        $this->assertAuthenticatedAs($teacher);

        app(GrantStudentSessionAction::class)->execute($teacher, $student, $group, null, null);

        $this->assertAuthenticatedAs($student);

        $this->get('/academia')->assertForbidden();
        $this->get('/mis-proyectos')->assertOk();
    }

    public function test_ending_a_delivered_session_closes_the_grant_and_logs_out(): void
    {
        $group = Group::factory()->create();
        $teacher = User::factory()->create()->assignRole('teacher');
        $student = User::factory()->create(['group_id' => $group->id])->assignRole('student');

        $this->actingAs($teacher);
        $grant = app(GrantStudentSessionAction::class)->execute($teacher, $student, $group, null, null);

        $this->post('/logout')->assertRedirect(route('login'));

        $this->assertNotNull($grant->fresh()->ended_at);
        $this->assertGuest();
    }

    public function test_a_normal_student_logout_with_no_active_grant_does_not_touch_any_grant(): void
    {
        $student = User::factory()->create()->assignRole('student');
        $this->actingAs($student);

        $this->post('/logout')->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertSame(0, StudentSessionGrant::count());
    }

    public function test_end_student_session_action_is_idempotent(): void
    {
        $grant = StudentSessionGrant::factory()->create(['ended_at' => now()->subHour()]);
        $originalEndedAt = $grant->ended_at;

        app(EndStudentSessionAction::class)->execute($grant->id);

        $this->assertTrue($grant->fresh()->ended_at->eq($originalEndedAt));
    }
}
