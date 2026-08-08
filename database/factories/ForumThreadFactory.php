<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Community\Models\ForumThread;
use App\Modules\Project\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ForumThread>
 */
class ForumThreadFactory extends Factory
{
    protected $model = ForumThread::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'phase_id' => null,
            'created_by' => User::factory(),
            'title' => fake()->sentence(6),
        ];
    }
}
