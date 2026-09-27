<?php

namespace Tests\Feature\Student;

use App\Models\User;
use App\Modules\Assessment\Models\Submission;
use App\Modules\Identity\Actions\GrantStudentSessionAction;
use App\Modules\Identity\Models\StudentSessionGrant;
use App\Modules\Institution\Models\Cycle;
use App\Modules\Institution\Models\Group;
use App\Modules\Institution\Models\SchoolGrade;
use App\Modules\Project\Models\ExpectedEvidence;
use App\Modules\Project\Models\Project;
use Database\Seeders\RoleLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ExpireDeliveredStudentSession como middleware persistente de Livewire
 * (AppServiceProvider::boot()): sin eso, solo corría en el GET inicial de la
 * página, y los POST /livewire/update posteriores (submit(), etc.) lo
 * saltaban -- una sesión entregada expirada seguía pudiendo escribir
 * mientras la pestaña siguiera abierta.
 *
 * HTTP real a propósito, NO Livewire::test(): Livewire solo aplica el
 * middleware persistente cuando la request pega de verdad contra su
 * endpoint de update (ver PersistentMiddleware::boot(), chequeo
 * isLivewireRoute()), así que Livewire::test() nunca lo ejercita. El
 * snapshot sale del HTML real de un GET a la página (mismo checksum que
 * vería el navegador), y el POST se arma a mano con ese snapshot.
 *
 * El control positivo (sesión dentro de la ventana, submit() SÍ crea la
 * entrega) prueba que el payload armado a mano es válido -- sin él, el test
 * negativo podría pasar por un payload mal formado en vez de por el
 * middleware.
 */
class DeliveredSessionLivewireUpdateExpirationTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private StudentSessionGrant $grant;

    private ExpectedEvidence $evidence;

    private string $pageUrl;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLevelSeeder::class);

        $cycle = Cycle::factory()->create();
        $grade = SchoolGrade::factory()->create(['cycle_id' => $cycle->id]);
        $group = Group::factory()->create(['cycle_id' => $cycle->id]);
        $teacher = User::factory()->create()->assignRole('teacher');
        $this->student = User::factory()->create([
            'school_grade_id' => $grade->id,
            'group_id' => $group->id,
        ])->assignRole('student');

        $project = Project::factory()->create(['cycle_id' => $cycle->id]);
        $this->evidence = ExpectedEvidence::factory()->create(['phase_id' => $project->phases()->first()->id]);

        $this->pageUrl = route('student.evidence.show', ['project' => $project, 'evidence' => $this->evidence]);

        $this->actingAs($teacher);
        $this->grant = app(GrantStudentSessionAction::class)->execute($teacher, $this->student, $group, null, null);
    }

    public function test_an_idle_delivered_session_is_expired_on_livewire_update_before_the_action_runs(): void
    {
        $snapshot = $this->evidenceShowSnapshot();

        // Simula 51 minutos sin actividad entre el GET y la interacción.
        session(['active_grant_last_seen_at' => now()->subMinutes(51)->toISOString()]);

        $response = $this->submitViaLivewireUpdate($snapshot);

        $response->assertRedirect(route('login'));
        $this->assertSame(0, Submission::count());
        $this->assertNotNull($this->grant->fresh()->ended_at);
        $this->assertGuest();
        $this->assertNull(session('active_grant_id'));
    }

    public function test_a_delivered_session_within_the_idle_window_can_still_submit_via_livewire_update(): void
    {
        $snapshot = $this->evidenceShowSnapshot();

        session(['active_grant_last_seen_at' => now()->subMinutes(10)->toISOString()]);

        $response = $this->submitViaLivewireUpdate($snapshot);

        $response->assertOk();
        $this->assertSame(1, Submission::where('student_id', $this->student->id)->count());
        $this->assertNull($this->grant->fresh()->ended_at);
        $this->assertAuthenticatedAs($this->student);
    }

    /**
     * El layout puede montar otros componentes Livewire antes del de la
     * página -- se elige el snapshot por nombre, no por posición.
     */
    private function evidenceShowSnapshot(): string
    {
        $html = $this->get($this->pageUrl)->assertOk()->getContent();

        preg_match_all('/wire:snapshot="([^"]+)"/', $html, $matches);

        foreach ($matches[1] as $encoded) {
            $snapshot = html_entity_decode($encoded, ENT_QUOTES);

            if (str_contains(json_decode($snapshot, true)['memo']['name'] ?? '', 'evidence-show')) {
                return $snapshot;
            }
        }

        $this->fail('No se encontró el snapshot de EvidenceShow en el HTML de la página.');
    }

    private function submitViaLivewireUpdate(string $snapshot)
    {
        return $this->withHeaders(['X-Livewire' => 'true'])->postJson('/livewire/update', [
            'components' => [[
                'snapshot' => $snapshot,
                'updates' => ['textContent' => 'Entrega desde una sesión entregada'],
                'calls' => [['path' => '', 'method' => 'submit', 'params' => []]],
            ]],
        ]);
    }
}
