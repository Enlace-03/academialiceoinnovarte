<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Project\Models\Project;
use App\Modules\Tracking\Models\PerformanceSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PerformanceSnapshot>
 */
class PerformanceSnapshotFactory extends Factory
{
    protected $model = PerformanceSnapshot::class;

    public function definition(): array
    {
        return [
            'student_id' => User::factory(),
            'project_id' => Project::factory(),
            'snapshot_date' => now()->toDateString(),
            'metrics' => ['progress_pct' => 0, 'qualitative_level_key' => null],
        ];
    }
}
