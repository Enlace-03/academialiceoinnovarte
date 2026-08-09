<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Project\Models\Project;
use App\Modules\Tracking\Models\StudentProgress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentProgress>
 */
class StudentProgressFactory extends Factory
{
    protected $model = StudentProgress::class;

    public function definition(): array
    {
        return [
            'student_id' => User::factory(),
            'project_id' => Project::factory(),
            'phase_id' => null,
            'progress_pct' => 0,
            'guides_completed' => 0,
            'guides_total' => 0,
            'forum_participations' => 0,
            'chat_participations' => 0,
            'evidences_submitted' => 0,
            'evidences_total' => 0,
            'status' => 'not_started',
        ];
    }
}
