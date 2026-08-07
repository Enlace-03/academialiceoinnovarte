<?php

namespace Database\Factories;

use App\Modules\Institution\Models\Group;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectTeam;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectTeam>
 */
class ProjectTeamFactory extends Factory
{
    protected $model = ProjectTeam::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'group_id' => Group::factory(),
            'name' => 'Equipo '.fake()->unique()->word(),
        ];
    }
}
