<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Institution\Models\Cycle;
use App\Modules\Project\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'cycle_id' => Cycle::factory(),
            'created_by_user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'problem_situation' => fake()->paragraphs(2, true),
            'guiding_question' => fake()->sentence().'?',
            'purpose' => fake()->paragraph(),
            'semester' => fake()->randomElement([1, 2]),
            'year' => (int) config('school.current_academic_year'),
            'suggested_duration_weeks' => fake()->numberBetween(4, 16),
            'expected_impact' => fake()->paragraph(),
        ];
    }
}
