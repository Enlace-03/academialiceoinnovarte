<?php

namespace Database\Factories;

use App\Modules\Assessment\Models\RubricLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RubricLevel>
 *
 * En la práctica los niveles vienen sembrados por RubricLevelSeeder — esta
 * factory es solo para pruebas que necesiten un nivel ad-hoc aislado.
 */
class RubricLevelFactory extends Factory
{
    protected $model = RubricLevel::class;

    public function definition(): array
    {
        $order = fake()->unique()->numberBetween(5, 200);

        return [
            'key' => 'nivel_'.$order,
            'label' => 'Nivel '.$order,
            'color' => fake()->hexColor(),
            'order' => $order,
        ];
    }
}
