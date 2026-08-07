<?php

namespace Tests\Feature\Project;

use App\Models\User;
use App\Modules\Institution\Models\Group;
use App\Modules\Project\Actions\UpdateStudentPhaseScheduleAction;
use App\Modules\Project\Models\Phase;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectTeam;
use App\Modules\Project\Models\StudentPhaseSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateStudentPhaseScheduleActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_save_does_not_increment_extension_count(): void
    {
        $student = User::factory()->create();
        $phase = Phase::factory()->create();

        $schedule = app(UpdateStudentPhaseScheduleAction::class)
            ->execute($student, $phase, '2026-01-01', '2026-01-15');

        $this->assertSame(0, $schedule->extension_count);
        $this->assertSame('2026-01-15', $schedule->end_date->toDateString());
    }

    public function test_changing_end_date_increments_extension_count(): void
    {
        $student = User::factory()->create();
        $phase = Phase::factory()->create();
        $action = app(UpdateStudentPhaseScheduleAction::class);

        $action->execute($student, $phase, '2026-01-01', '2026-01-15');
        $updated = $action->execute($student, $phase, '2026-01-01', '2026-01-22');

        $this->assertSame(1, $updated->extension_count);

        $updatedAgain = $action->execute($student, $phase, '2026-01-01', '2026-01-29');
        $this->assertSame(2, $updatedAgain->extension_count);
    }

    public function test_changing_start_date_only_does_not_increment_extension_count(): void
    {
        $student = User::factory()->create();
        $phase = Phase::factory()->create();
        $action = app(UpdateStudentPhaseScheduleAction::class);

        $action->execute($student, $phase, '2026-01-01', '2026-01-15');
        $updated = $action->execute($student, $phase, '2026-01-03', '2026-01-15');

        $this->assertSame(0, $updated->extension_count);
    }

    public function test_updating_a_teammates_schedule_propagates_the_same_dates(): void
    {
        $project = Project::factory()->create();
        $phase = $project->phases()->first();
        $team = ProjectTeam::factory()->create([
            'project_id' => $project->id,
            'group_id' => Group::factory()->create()->id,
        ]);

        $s1 = User::factory()->create();
        $s2 = User::factory()->create();
        $team->users()->attach([
            $s1->id => ['role_in_team' => 'investigador'],
            $s2->id => ['role_in_team' => 'vocero'],
        ]);

        app(UpdateStudentPhaseScheduleAction::class)->execute($s1, $phase, '2026-01-01', '2026-01-15');

        $teammateSchedule = StudentPhaseSchedule::where('student_id', $s2->id)
            ->where('phase_id', $phase->id)
            ->first();

        $this->assertNotNull($teammateSchedule);
        $this->assertSame('2026-01-15', $teammateSchedule->end_date->toDateString());
    }

    public function test_propagation_also_increments_teammates_extension_count_when_their_end_date_changes(): void
    {
        $project = Project::factory()->create();
        $phase = $project->phases()->first();
        $team = ProjectTeam::factory()->create([
            'project_id' => $project->id,
            'group_id' => Group::factory()->create()->id,
        ]);

        $s1 = User::factory()->create();
        $s2 = User::factory()->create();
        $team->users()->attach([$s1->id, $s2->id]);

        $action = app(UpdateStudentPhaseScheduleAction::class);
        $action->execute($s1, $phase, '2026-01-01', '2026-01-15');
        $action->execute($s1, $phase, '2026-01-01', '2026-01-22');

        $teammateSchedule = StudentPhaseSchedule::where('student_id', $s2->id)
            ->where('phase_id', $phase->id)
            ->first();

        $this->assertSame(1, $teammateSchedule->extension_count);
    }

    public function test_student_not_in_a_team_does_not_propagate_to_anyone(): void
    {
        $student = User::factory()->create();
        $phase = Phase::factory()->create();

        app(UpdateStudentPhaseScheduleAction::class)->execute($student, $phase, '2026-01-01', '2026-01-15');

        $this->assertDatabaseCount('student_phase_schedules', 1);
    }
}
