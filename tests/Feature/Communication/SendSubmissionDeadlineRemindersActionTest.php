<?php

namespace Tests\Feature\Communication;

use App\Models\User;
use App\Modules\Assessment\Models\Submission;
use App\Modules\Communication\Actions\SendSubmissionDeadlineRemindersAction;
use App\Modules\Communication\Models\SentDeadlineReminder;
use App\Modules\Communication\Notifications\SubmissionDeadlineReminder;
use App\Modules\Institution\Models\Cycle;
use App\Modules\Institution\Models\Institution;
use App\Modules\Institution\Models\SchoolGrade;
use App\Modules\Project\Models\ExpectedEvidence;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\StudentPhaseSchedule;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendSubmissionDeadlineRemindersActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Institution::factory()->create();
    }

    private function studentInCycle(int $cycleOrder): User
    {
        $cycle = Cycle::factory()->create(['order' => $cycleOrder]);
        $schoolGrade = SchoolGrade::factory()->create(['cycle_id' => $cycle->id]);

        return User::factory()->create(['school_grade_id' => $schoolGrade->id])->assignRole('student');
    }

    private function scheduleDueIn(User $student, int $days): StudentPhaseSchedule
    {
        $project = Project::factory()->create();
        $phase = $project->phases()->first();
        ExpectedEvidence::factory()->create(['phase_id' => $phase->id]);

        return StudentPhaseSchedule::factory()->create([
            'student_id' => $student->id,
            'phase_id' => $phase->id,
            'end_date' => now()->addDays($days)->toDateString(),
        ]);
    }

    public function test_a_student_in_cycles_3_4_is_notified_directly(): void
    {
        $student = $this->studentInCycle(3);
        $this->scheduleDueIn($student, 3);

        Notification::fake();

        app(SendSubmissionDeadlineRemindersAction::class)->execute();

        Notification::assertSentTo($student, SubmissionDeadlineReminder::class);
    }

    public function test_a_student_in_cycles_1_2_is_not_notified_directly_their_guardians_are(): void
    {
        $student = $this->studentInCycle(1);
        $guardian = User::factory()->create()->assignRole('parent');
        $guardian->children()->attach($student->id, ['relationship' => 'madre']);
        $this->scheduleDueIn($student, 1);

        Notification::fake();

        app(SendSubmissionDeadlineRemindersAction::class)->execute();

        Notification::assertSentTo($guardian, SubmissionDeadlineReminder::class);
        Notification::assertNotSentTo($student, SubmissionDeadlineReminder::class);
    }

    public function test_guardians_receive_only_by_mail_never_in_platform(): void
    {
        $student = $this->studentInCycle(1);
        $schedule = $this->scheduleDueIn($student, 1);
        $guardian = User::factory()->create()->assignRole('parent');

        $notification = new SubmissionDeadlineReminder($schedule, 1);

        $this->assertSame(['mail'], $notification->via($guardian));
        $this->assertSame(['mail', 'database'], $notification->via($student));
    }

    public function test_running_the_job_twice_the_same_day_does_not_duplicate_the_reminder(): void
    {
        $student = $this->studentInCycle(3);
        $this->scheduleDueIn($student, 3);

        Notification::fake();

        app(SendSubmissionDeadlineRemindersAction::class)->execute();
        app(SendSubmissionDeadlineRemindersAction::class)->execute();

        $this->assertSame(1, SentDeadlineReminder::count());
        Notification::assertSentToTimes($student, SubmissionDeadlineReminder::class, 1);
    }

    public function test_no_reminder_is_sent_when_the_student_already_submitted_everything(): void
    {
        $student = $this->studentInCycle(3);
        $schedule = $this->scheduleDueIn($student, 3);
        $evidence = $schedule->phase->expectedEvidences()->first();

        Submission::factory()->create([
            'expected_evidence_id' => $evidence->id,
            'student_id' => $student->id,
            'status' => 'submitted',
        ]);

        Notification::fake();

        app(SendSubmissionDeadlineRemindersAction::class)->execute();

        Notification::assertNothingSent();
        $this->assertSame(0, SentDeadlineReminder::count());
    }
}
