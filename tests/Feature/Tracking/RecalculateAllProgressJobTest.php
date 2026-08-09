<?php

namespace Tests\Feature\Tracking;

use App\Models\User;
use App\Modules\Institution\Models\Cycle;
use App\Modules\Institution\Models\SchoolGrade;
use App\Modules\Project\Models\Project;
use App\Modules\Tracking\Jobs\RecalculateAllProgressJob;
use App\Modules\Tracking\Jobs\RecalculateStudentProgressJob;
use Database\Seeders\RoleLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RecalculateAllProgressJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLevelSeeder::class);
    }

    public function test_dispatches_a_job_per_eligible_student_and_project_pair(): void
    {
        Queue::fake();

        $cycle = Cycle::factory()->create();
        $grade = SchoolGrade::factory()->create(['cycle_id' => $cycle->id]);
        $studentInCycle = User::factory()->create(['school_grade_id' => $grade->id])->assignRole('student');
        $project = Project::factory()->create(['cycle_id' => $cycle->id]);

        $otherCycle = Cycle::factory()->create();
        $otherGrade = SchoolGrade::factory()->create(['cycle_id' => $otherCycle->id]);
        $studentOtherCycle = User::factory()->create(['school_grade_id' => $otherGrade->id])->assignRole('student');

        app(RecalculateAllProgressJob::class)->handle();

        Queue::assertPushed(
            RecalculateStudentProgressJob::class,
            fn ($job) => $job->student->is($studentInCycle) && $job->project->is($project),
        );
        Queue::assertNotPushed(
            RecalculateStudentProgressJob::class,
            fn ($job) => $job->student->is($studentOtherCycle),
        );
    }

    public function test_a_teacher_is_never_dispatched_as_a_student(): void
    {
        Queue::fake();

        $cycle = Cycle::factory()->create();
        $project = Project::factory()->create(['cycle_id' => $cycle->id]);
        $teacher = User::factory()->create()->assignRole('teacher');

        app(RecalculateAllProgressJob::class)->handle();

        Queue::assertNotPushed(
            RecalculateStudentProgressJob::class,
            fn ($job) => $job->student->is($teacher),
        );
    }
}
