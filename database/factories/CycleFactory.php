<?php

namespace Database\Factories;

use App\Modules\Institution\Models\Cycle;
use App\Modules\Institution\Models\Institution;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cycle>
 */
class CycleFactory extends Factory
{
    protected $model = Cycle::class;

    /**
     * Sin ->unique() a propósito (bug real encontrado en la suite completa,
     * Hito de estrellas): la restricción real de unicidad es
     * unique(['institution_id', 'order']) a nivel de BD -- Faker's
     * unique() rastrea valores usados de forma global al proceso, no por
     * institución, así que se agota para siempre después de solo 4 Cycles
     * creados en TODA la suite de tests (institution_id es
     * Institution::factory() anidada por defecto, una institución nueva
     * por cada Cycle, así que la colisión real que ->unique() pretendía
     * evitar prácticamente nunca ocurre).
     */
    public function definition(): array
    {
        $order = fake()->numberBetween(1, 4);

        return [
            'institution_id' => Institution::factory(),
            'name' => "Ciclo {$order}",
            'order' => $order,
            'description' => fake()->sentence(),
        ];
    }
}
