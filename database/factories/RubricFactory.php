<?php

namespace Database\Factories;

use App\Modules\Assessment\Models\Rubric;
use App\Modules\Institution\Models\Institution;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rubric>
 */
class RubricFactory extends Factory
{
    protected $model = Rubric::class;

    public function definition(): array
    {
        return [
            'institution_id' => Institution::factory(),
            'name' => fake()->sentence(3),
            'description' => fake()->paragraph(),
        ];
    }
}
