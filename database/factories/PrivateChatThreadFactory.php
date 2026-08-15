<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Community\Models\PrivateChatThread;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectTeam;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PrivateChatThread>
 */
class PrivateChatThreadFactory extends Factory
{
    protected $model = PrivateChatThread::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'type' => 'individual',
            'student_id' => User::factory(),
            'team_id' => null,
        ];
    }

    public function team(): static
    {
        return $this->state(fn (): array => [
            'type' => 'team',
            'student_id' => null,
            'team_id' => ProjectTeam::factory(),
        ]);
    }
}
