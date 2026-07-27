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

    public function definition(): array
    {
        $order = fake()->unique()->numberBetween(1, 4);

        return [
            'institution_id' => Institution::factory(),
            'name' => "Ciclo {$order}",
            'order' => $order,
            'description' => fake()->sentence(),
        ];
    }
}
