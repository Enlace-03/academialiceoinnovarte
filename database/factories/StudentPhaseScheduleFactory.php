<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Project\Models\Phase;
use App\Modules\Project\Models\StudentPhaseSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentPhaseSchedule>
 */
class StudentPhaseScheduleFactory extends Factory
{
    protected $model = StudentPhaseSchedule::class;

    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-2 weeks', 'now');

        return [
            'student_id' => User::factory(),
            'phase_id' => Phase::factory(),
            'start_date' => $start->format('Y-m-d'),
            'end_date' => fake()->dateTimeBetween($start, '+4 weeks')->format('Y-m-d'),
            'extension_count' => 0,
        ];
    }
}
