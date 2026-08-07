<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

// Catálogo global de niveles de rúbrica — escala vigente confirmada en el
// Hito 2. Único set de etiquetas correcto; cualquier otro (inglés, u otro
// texto en español) que aparezca en código o skills viejas queda descartado.
final class RubricLevelSeeder extends Seeder
{
    public function run(): void
    {
        $levels = [
            ['key' => 'inicio', 'label' => 'Inicio', 'color' => '#EF4444', 'order' => 1],
            ['key' => 'en_proceso', 'label' => 'En proceso', 'color' => '#F97316', 'order' => 2],
            ['key' => 'logro_esperado', 'label' => 'Logro esperado', 'color' => '#EAB308', 'order' => 3],
            ['key' => 'logro_destacado', 'label' => 'Logro destacado', 'color' => '#22C55E', 'order' => 4],
        ];

        foreach ($levels as $level) {
            DB::table('rubric_levels')->updateOrInsert(
                ['key' => $level['key']],
                [...$level, 'created_at' => now(), 'updated_at' => now()],
            );
        }
    }
}
