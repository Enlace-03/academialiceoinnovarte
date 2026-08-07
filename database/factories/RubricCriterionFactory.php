<?php

namespace Database\Factories;

use App\Modules\Assessment\Models\Rubric;
use App\Modules\Assessment\Models\RubricCriterion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RubricCriterion>
 */
class RubricCriterionFactory extends Factory
{
    protected $model = RubricCriterion::class;

    public function definition(): array
    {
        return [
            'rubric_id' => Rubric::factory(),
            'name' => fake()->sentence(3),
            'level_descriptions' => [
                'inicio' => fake()->sentence(),
                'en_proceso' => fake()->sentence(),
                'logro_esperado' => fake()->sentence(),
                'logro_destacado' => fake()->sentence(),
            ],
            'position' => fake()->unique()->numberBetween(1, 200),
        ];
    }
}
