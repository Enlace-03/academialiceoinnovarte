<?php

namespace Tests\Feature\Student;

use App\Models\User;
use App\Modules\Identity\Actions\GrantStudentSessionAction;
use App\Modules\Identity\Models\StudentSessionGrant;
use App\Modules\Institution\Models\Group;
use Database\Seeders\RoleLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ExpireDeliveredStudentSession::isPurePoll(): un wire:poll sin expresión (los
 * tres del proyecto -- notification-bell, group-chat, private-chat-panel)
 * dispara $wire.$commit() del cliente, que viaja SIN 'calls' y SIN 'updates'
 * (verificado contra vendor/livewire/livewire/dist/livewire.js, no asumido).
 * Sin distinguir esto de una interacción real, un sondeo periódico sin
 * actividad del usuario mantenía 'active_grant_last_seen_at' fresco para
 * siempre y la sesión entregada nunca expiraba mientras la pestaña siguiera
 * abierta.
 */
class DeliveredSessionPollExpirationTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private StudentSessionGrant $grant;

    private string $chatUrl;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLevelSeeder::class);

        $group = Group::factory()->create();
        $teacher = User::factory()->create()->assignRole('teacher');
        $this->student = User::factory()->create(['group_id' => $group->id])->assignRole('student');

        $this->chatUrl = route('student.chat');

        $this->actingAs($teacher);
        $this->grant = app(GrantStudentSessionAction::class)->execute($teacher, $this->student, $group, null, null);
    }

    public function test_a_poll_after_51_idle_minutes_still_closes_the_session(): void
    {
        $snapshot = $this->groupChatSnapshot();

        session(['active_grant_last_seen_at' => now()->subMinutes(51)->toISOString()]);

        $response = $this->pollViaLivewireUpdate($snapshot);

        $response->assertRedirect(route('login'));
        $this->assertNotNull($this->grant->fresh()->ended_at);
        $this->assertGuest();
    }

    /**
     * Sondeos cada pocos minutos sin actividad real NUNCA deben renovar la
     * marca -- si lo hicieran (código antes del fix), esta secuencia jamás
     * cerraría la sesión porque cada sondeo reiniciaría el conteo de
     * inactividad.
     */
    public function test_repeated_polling_without_real_activity_eventually_closes_the_session(): void
    {
        $snapshot = $this->groupChatSnapshot();
        $start = now();

        session(['active_grant_last_seen_at' => $start->toISOString()]);

        $this->travelTo($start->copy()->addMinutes(13));
        $this->pollViaLivewireUpdate($snapshot)->assertOk();
        $this->assertNull($this->grant->fresh()->ended_at);

        $this->travelTo($start->copy()->addMinutes(26));
        $this->pollViaLivewireUpdate($snapshot)->assertOk();
        $this->assertNull($this->grant->fresh()->ended_at);

        $this->travelTo($start->copy()->addMinutes(39));
        $this->pollViaLivewireUpdate($snapshot)->assertOk();
        $this->assertNull($this->grant->fresh()->ended_at);

        // 52 minutos desde la ÚNICA actividad real (el momento del grant) --
        // ninguno de los tres sondeos anteriores debió haber movido la marca.
        $this->travelTo($start->copy()->addMinutes(52));
        $response = $this->pollViaLivewireUpdate($snapshot);

        $response->assertRedirect(route('login'));
        $this->assertNotNull($this->grant->fresh()->ended_at);
        $this->assertGuest();
    }

    public function test_a_real_interaction_renews_the_mark(): void
    {
        $snapshot = $this->groupChatSnapshot();

        session(['active_grant_last_seen_at' => now()->subMinutes(30)->toISOString()]);

        $response = $this->withHeaders(['X-Livewire' => 'true'])->postJson('/livewire/update', [
            'components' => [[
                'snapshot' => $snapshot,
                'updates' => ['content' => 'Hola grupo, desde una sesión entregada'],
                'calls' => [['path' => '', 'method' => 'send', 'params' => []]],
            ]],
        ]);

        $response->assertOk();
        $this->assertNull($this->grant->fresh()->ended_at);
        $this->assertAuthenticatedAs($this->student);

        $renewedAt = session('active_grant_last_seen_at');
        $this->assertNotNull($renewedAt);
        $this->assertLessThan(5, now()->diffInSeconds(\Carbon\Carbon::parse($renewedAt)));
    }

    private function groupChatSnapshot(): string
    {
        $html = $this->get($this->chatUrl)->assertOk()->getContent();

        preg_match_all('/wire:snapshot="([^"]+)"/', $html, $matches);

        foreach ($matches[1] as $encoded) {
            $snapshot = html_entity_decode($encoded, ENT_QUOTES);

            if (str_contains(json_decode($snapshot, true)['memo']['name'] ?? '', 'group-chat')) {
                return $snapshot;
            }
        }

        $this->fail('No se encontró el snapshot de GroupChat en el HTML de la página.');
    }

    private function pollViaLivewireUpdate(string $snapshot)
    {
        return $this->withHeaders(['X-Livewire' => 'true'])->postJson('/livewire/update', [
            'components' => [[
                'snapshot' => $snapshot,
                'updates' => [],
                'calls' => [],
            ]],
        ]);
    }
}
