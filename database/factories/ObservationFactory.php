<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Assessment\Models\Observation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Observation>
 */
class ObservationFactory extends Factory
{
    protected $model = Observation::class;

    public function definition(): array
    {
        return [
            'student_id' => User::factory(),
            'teacher_id' => User::factory(),
            'project_id' => null,
            'content' => fake()->paragraph(),
            'visible_to_parents' => true,
        ];
    }
}
