<?php

namespace Database\Factories;

use App\Modules\Institution\Models\Institution;
use App\Modules\Institution\Models\SchoolGrade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolGrade>
 */
class SchoolGradeFactory extends Factory
{
    protected $model = SchoolGrade::class;

    public function definition(): array
    {
        $level = fake()->unique()->numberBetween(0, 9);

        return [
            'institution_id' => Institution::factory(),
            'cycle_id' => null,
            'name' => "{$level}°",
            'level' => $level,
            'is_active' => true,
        ];
    }
}
