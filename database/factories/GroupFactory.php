<?php

namespace Database\Factories;

use App\Modules\Institution\Models\Group;
use App\Modules\Institution\Models\SchoolGrade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Group>
 */
class GroupFactory extends Factory
{
    protected $model = Group::class;

    public function definition(): array
    {
        return [
            'school_grade_id' => SchoolGrade::factory(),
            'name' => fake()->randomElement(['A', 'B']),
            'year' => (int) config('school.current_academic_year'),
        ];
    }
}
