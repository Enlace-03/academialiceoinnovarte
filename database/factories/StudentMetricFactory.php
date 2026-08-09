<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Project\Models\Project;
use App\Modules\Tracking\Models\StudentMetric;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentMetric>
 */
class StudentMetricFactory extends Factory
{
    protected $model = StudentMetric::class;

    public function definition(): array
    {
        return [
            'student_id' => User::factory(),
            'project_id' => Project::factory(),
            'progress_pct' => 0,
            'avg_rubric_value' => null,
            'weekly_pace' => null,
            'last_active_at' => null,
            'inactive_days' => 0,
            'risk_level' => 'low',
            'risk_score' => 0,
        ];
    }
}
