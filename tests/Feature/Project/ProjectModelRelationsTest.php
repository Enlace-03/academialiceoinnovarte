<?php

namespace Tests\Feature\Project;

use App\Models\User;
use App\Modules\Institution\Models\Cycle;
use App\Modules\Institution\Models\Group;
use App\Modules\Institution\Models\ThinkingField;
use App\Modules\Project\Models\ExpectedEvidence;
use App\Modules\Project\Models\Guide;
use App\Modules\Project\Models\Phase;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectTeam;
use App\Modules\Project\Models\Resource as ProjectResourceModel;
use App\Modules\Project\Models\StudentPhaseSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectModelRelationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_relations(): void
    {
        $cycle = Cycle::factory()->create();
        $teacher = User::factory()->create();
        $thinkingField = ThinkingField::factory()->create();

        $project = Project::factory()->create([
            'cycle_id' => $cycle->id,
            'created_by_user_id' => $teacher->id,
        ]);
        $project->thinkingFields()->attach($thinkingField->id);
        $team = ProjectTeam::factory()->create(['project_id' => $project->id]);

        $this->assertTrue($project->cycle->is($cycle));
        $this->assertTrue($project->createdBy->is($teacher));
        $this->assertTrue($project->thinkingFields->contains($thinkingField));
        $this->assertTrue($project->teams->contains($team));
    }

    public function test_phase_relations(): void
    {
        $project = Project::factory()->create();
        $phase = $project->phases()->first();

        $guide = Guide::factory()->for($phase)->create();
        $resource = ProjectResourceModel::factory()->for($phase)->create();
        $evidence = ExpectedEvidence::factory()->for($phase)->create();

        $this->assertTrue($phase->project->is($project));
        $this->assertTrue($phase->guides->contains($guide));
        $this->assertTrue($phase->resources->contains($resource));
        $this->assertTrue($phase->expectedEvidences->contains($evidence));
    }

    public function test_guide_relations(): void
    {
        $guide = Guide::factory()->create();
        $resource = ProjectResourceModel::factory()->create([
            'phase_id' => $guide->phase_id,
            'guide_id' => $guide->id,
        ]);

        $this->assertInstanceOf(Phase::class, $guide->phase);
        $this->assertTrue($guide->resources->contains($resource));
    }

    public function test_resource_relations(): void
    {
        $resource = ProjectResourceModel::factory()->create();

        $this->assertInstanceOf(Phase::class, $resource->phase);
        $this->assertNull($resource->guide);
    }

    public function test_project_team_relations(): void
    {
        $group = Group::factory()->create();
        $project = Project::factory()->create();
        $team = ProjectTeam::factory()->create(['project_id' => $project->id, 'group_id' => $group->id]);
        $student = User::factory()->create();
        $team->users()->attach($student->id, ['role_in_team' => 'vocero']);

        $this->assertTrue($team->project->is($project));
        $this->assertTrue($team->group->is($group));
        $this->assertTrue($team->users->contains($student));
        $this->assertSame('vocero', $team->users->first()->pivot->role_in_team);
    }

    public function test_student_phase_schedule_relations(): void
    {
        $student = User::factory()->create();
        $phase = Phase::factory()->create();
        $schedule = StudentPhaseSchedule::factory()->create([
            'student_id' => $student->id,
            'phase_id' => $phase->id,
        ]);

        $this->assertTrue($schedule->student->is($student));
        $this->assertTrue($schedule->phase->is($phase));
    }
}
