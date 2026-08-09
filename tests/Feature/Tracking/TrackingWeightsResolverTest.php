<?php

namespace Tests\Feature\Tracking;

use App\Modules\Institution\Models\Cycle;
use App\Modules\Institution\Models\Institution;
use App\Modules\Institution\Models\InstitutionSetting;
use App\Modules\Tracking\Actions\TrackingWeightsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cadena de 3 niveles (decisión confirmada del Hito 4):
 * override de ciclo -> override global -> config('tracking.progress_weights').
 */
class TrackingWeightsResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // InstitutionSetting::get()/set() exigen una fila de Institution
        // (institution_id no es nullable) -- mismo requisito que ya usa
        // InstitutionSettingsPageTest.
        Institution::factory()->create();
    }

    public function test_falls_back_to_config_default_when_nothing_is_overridden(): void
    {
        $cycle = Cycle::factory()->create();

        $weights = app(TrackingWeightsResolver::class)->forCycle($cycle);

        $this->assertSame(config('tracking.progress_weights'), $weights);
    }

    public function test_uses_the_global_override_when_no_cycle_specific_override_exists(): void
    {
        $cycle = Cycle::factory()->create();

        InstitutionSetting::set(TrackingWeightsResolver::GLOBAL_KEY, json_encode([
            'evidencias' => 50, 'foro' => 30, 'chat' => 20,
        ]));

        $weights = app(TrackingWeightsResolver::class)->forCycle($cycle);

        $this->assertSame(['evidencias' => 50, 'foro' => 30, 'chat' => 20], $weights);
    }

    public function test_cycle_specific_override_wins_over_the_global_override(): void
    {
        $cycle = Cycle::factory()->create();

        InstitutionSetting::set(TrackingWeightsResolver::GLOBAL_KEY, json_encode([
            'evidencias' => 50, 'foro' => 30, 'chat' => 20,
        ]));
        InstitutionSetting::set(TrackingWeightsResolver::cycleKey($cycle->id), json_encode([
            'evidencias' => 70, 'foro' => 20, 'chat' => 10,
        ]));

        $weights = app(TrackingWeightsResolver::class)->forCycle($cycle);

        $this->assertSame(['evidencias' => 70, 'foro' => 20, 'chat' => 10], $weights);
    }

    public function test_a_different_cycle_without_its_own_override_is_unaffected_by_another_cycles_override(): void
    {
        $cycleA = Cycle::factory()->create();
        $cycleB = Cycle::factory()->create();

        InstitutionSetting::set(TrackingWeightsResolver::cycleKey($cycleA->id), json_encode([
            'evidencias' => 70, 'foro' => 20, 'chat' => 10,
        ]));

        $weights = app(TrackingWeightsResolver::class)->forCycle($cycleB);

        $this->assertSame(config('tracking.progress_weights'), $weights);
    }
}
