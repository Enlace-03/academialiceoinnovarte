<?php

namespace Tests\Unit\Institution;

use App\Modules\Institution\Models\Institution;
use App\Modules\Institution\Models\InstitutionSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InstitutionSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_returns_the_default_when_no_row_exists(): void
    {
        Institution::factory()->create();

        $this->assertSame('2026', InstitutionSetting::get('current_academic_year', '2026'));
    }

    public function test_get_returns_the_stored_value_when_a_row_exists(): void
    {
        $institution = Institution::factory()->create();

        InstitutionSetting::factory()->for($institution)->create([
            'key' => 'current_academic_year',
            'value' => '2027',
        ]);

        $this->assertSame('2027', InstitutionSetting::get('current_academic_year', '2026'));
    }

    public function test_get_caches_the_result_and_does_not_hit_the_database_again(): void
    {
        $institution = Institution::factory()->create();

        // Primera lectura: no hay fila, cachea el "miss" (se resuelve al default afuera del cache).
        $this->assertSame('2026', InstitutionSetting::get('current_academic_year', '2026'));

        // Se inserta la fila directo en BD, sin pasar por InstitutionSetting::set()
        // (que es lo único que invalida el caché). Si get() sigue cacheado, no debe verla.
        DB::table('institution_settings')->insert([
            'institution_id' => $institution->id,
            'key' => 'current_academic_year',
            'value' => '2027',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(
            '2026',
            InstitutionSetting::get('current_academic_year', '2026'),
            'El valor cacheado no debería cambiar por una escritura directa a la base de datos.'
        );
    }

    public function test_set_updates_the_value_and_invalidates_the_cache(): void
    {
        $institution = Institution::factory()->create();

        $this->assertSame('2026', InstitutionSetting::get('current_academic_year', '2026'));

        InstitutionSetting::set('current_academic_year', '2027');

        $this->assertSame('2027', InstitutionSetting::get('current_academic_year', '2026'));

        $this->assertDatabaseHas('institution_settings', [
            'institution_id' => $institution->id,
            'key' => 'current_academic_year',
            'value' => '2027',
        ]);
    }
}
