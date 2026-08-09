<?php

namespace Tests\Feature\Communication;

use App\Models\User;
use App\Modules\Assessment\Actions\EvaluateSubmissionAction;
use App\Modules\Assessment\Actions\RegisterSubmissionAction;
use App\Modules\Assessment\Models\RubricCriterion;
use App\Modules\Assessment\Models\RubricLevel;
use App\Modules\Assessment\Models\Submission;
use App\Modules\Communication\Notifications\EvaluationReceived;
use App\Modules\Communication\Notifications\ForumReplyReceived;
use App\Modules\Communication\Notifications\SubmissionDeadlineReminder;
use App\Modules\Community\Actions\CreateForumPostAction;
use App\Modules\Community\Models\ForumPost;
use App\Modules\Community\Models\ForumThread;
use App\Modules\Institution\Models\Cycle;
use App\Modules\Institution\Models\Institution;
use App\Modules\Institution\Models\SchoolGrade;
use App\Modules\Project\Models\ExpectedEvidence;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\StudentPhaseSchedule;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RubricLevelSeeder;
use Filament\Notifications\DatabaseNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * URL de destino de cada notificación (segunda vuelta del Hito 5). No
 * repite la cobertura de aislamiento por ciclo/autoridad de proyecto ya
 * probada en NotificationIsolationTest -- esto verifica específicamente
 * que la URL generada sea la correcta, y que esa URL en sí respete la
 * autorización real del recurso al que apunta (403, no contenido filtrado).
 */
class NotificationActionUrlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RubricLevelSeeder::class);
        Institution::factory()->create();
    }

    private function studentInCycle(int $cycleOrder): User
    {
        $cycle = Cycle::factory()->create(['order' => $cycleOrder]);
        $schoolGrade = SchoolGrade::factory()->create(['cycle_id' => $cycle->id]);

        return User::factory()->create(['school_grade_id' => $schoolGrade->id])->assignRole('student');
    }

    public function test_forum_reply_received_links_to_the_real_thread(): void
    {
        $rootAuthor = User::factory()->create()->assignRole('student');
        $replier = User::factory()->create()->assignRole('student');
        $thread = ForumThread::factory()->create();
        $rootPost = ForumPost::factory()->create(['forum_thread_id' => $thread->id, 'user_id' => $rootAuthor->id]);

        Notification::fake();

        app(CreateForumPostAction::class)->execute($thread, $replier, [
            'content' => 'respuesta',
            'parent_post_id' => $rootPost->id,
        ]);

        $expectedUrl = route('student.forum.show', ['project' => $thread->project->uuid, 'thread' => $thread->uuid]);

        Notification::assertSentTo($rootAuthor, ForumReplyReceived::class, function (ForumReplyReceived $notification) use ($rootAuthor, $expectedUrl) {
            return $notification->toArray($rootAuthor)['action_url'] === $expectedUrl;
        });
    }

    public function test_evaluation_received_links_the_direct_student_to_the_phase_anchor(): void
    {
        $student = $this->studentInCycle(3);
        $teacher = User::factory()->create()->assignRole('teacher');
        $project = Project::factory()->create(['created_by_user_id' => $teacher->id]);
        $phase = $project->phases()->first();
        $evidence = ExpectedEvidence::factory()->create(['phase_id' => $phase->id]);
        $submission = Submission::factory()->create(['expected_evidence_id' => $evidence->id, 'student_id' => $student->id]);
        $criterion = RubricCriterion::factory()->create();
        $level = RubricLevel::where('key', 'logro_esperado')->firstOrFail();

        Notification::fake();

        app(EvaluateSubmissionAction::class)->execute($teacher, $submission, [$criterion->id => $level->id]);

        $expectedUrl = route('student.projects.show', $project->uuid).'#fase-'.$phase->id;

        Notification::assertSentTo($student, EvaluationReceived::class, function (EvaluationReceived $notification) use ($student, $expectedUrl) {
            return $notification->toArray($student)['action_url'] === $expectedUrl;
        });
    }

    public function test_evaluation_received_always_links_the_guardian_to_their_own_dashboard(): void
    {
        $student = $this->studentInCycle(1);
        $guardian = User::factory()->create()->assignRole('parent');
        $guardian->children()->attach($student->id, ['relationship' => 'madre']);

        $teacher = User::factory()->create()->assignRole('teacher');
        $project = Project::factory()->create(['created_by_user_id' => $teacher->id]);
        $phase = $project->phases()->first();
        $evidence = ExpectedEvidence::factory()->create(['phase_id' => $phase->id]);
        $submission = Submission::factory()->create(['expected_evidence_id' => $evidence->id, 'student_id' => $student->id]);
        $criterion = RubricCriterion::factory()->create();
        $level = RubricLevel::where('key', 'logro_esperado')->firstOrFail();

        Notification::fake();

        $evaluation = app(EvaluateSubmissionAction::class)->execute($teacher, $submission, [$criterion->id => $level->id]);

        $notification = new EvaluationReceived($evaluation);
        $this->assertSame(route('portal.home'), $notification->toArray($guardian)['action_url']);
    }

    public function test_deadline_reminder_links_the_direct_student_to_the_phase_anchor(): void
    {
        $student = $this->studentInCycle(3);
        $project = Project::factory()->create();
        $phase = $project->phases()->first();
        ExpectedEvidence::factory()->create(['phase_id' => $phase->id]);
        $schedule = StudentPhaseSchedule::factory()->create([
            'student_id' => $student->id,
            'phase_id' => $phase->id,
            'end_date' => now()->addDays(3)->toDateString(),
        ]);

        $notification = new SubmissionDeadlineReminder($schedule, 3);
        $expectedUrl = route('student.projects.show', $project->uuid).'#fase-'.$phase->id;

        $this->assertSame($expectedUrl, $notification->toArray($student)['action_url']);
    }

    public function test_deadline_reminder_always_links_the_guardian_to_their_own_dashboard(): void
    {
        $student = $this->studentInCycle(1);
        $guardian = User::factory()->create()->assignRole('parent');
        $project = Project::factory()->create();
        $phase = $project->phases()->first();
        $schedule = StudentPhaseSchedule::factory()->create([
            'student_id' => $student->id,
            'phase_id' => $phase->id,
            'end_date' => now()->addDays(3)->toDateString(),
        ]);

        $notification = new SubmissionDeadlineReminder($schedule, 3);

        $this->assertSame(route('portal.home'), $notification->toArray($guardian)['action_url']);
    }

    public function test_new_submission_notification_links_the_teacher_to_the_project_edit_page(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');
        $project = Project::factory()->create(['created_by_user_id' => $teacher->id]);
        $phase = $project->phases()->first();
        $evidence = ExpectedEvidence::factory()->create(['phase_id' => $phase->id]);
        $student = User::factory()->create()->assignRole('student');

        Notification::fake();

        app(RegisterSubmissionAction::class)->execute($evidence, $student, []);

        $expectedUrl = route('filament.academic.resources.projects.edit', ['record' => $project->id]);

        Notification::assertSentTo($teacher, DatabaseNotification::class, function (DatabaseNotification $notification) use ($expectedUrl) {
            return ($notification->data['actions'][0]['url'] ?? null) === $expectedUrl;
        });
    }

    /**
     * El destino en sí es lo que protege, no la notificación: un estudiante
     * de otro ciclo con el mismo enlace (ej. reenviado, copiado) recibe 403,
     * nunca ve contenido filtrado. Misma ProjectPolicy que ya usa el resto
     * del portal de estudiante (StudentForumFlowTest la cubre en general);
     * esto confirma que la URL real que generamos cae bajo esa misma regla.
     */
    public function test_clicking_a_forum_reply_link_as_a_student_from_another_cycle_returns_403(): void
    {
        $ownCycle = Cycle::factory()->create();
        $ownGrade = SchoolGrade::factory()->create(['cycle_id' => $ownCycle->id]);
        $rootAuthor = User::factory()->create(['school_grade_id' => $ownGrade->id])->assignRole('student');
        $project = Project::factory()->create(['cycle_id' => $ownCycle->id]);
        $thread = ForumThread::factory()->create(['project_id' => $project->id]);
        $rootPost = ForumPost::factory()->create(['forum_thread_id' => $thread->id, 'user_id' => $rootAuthor->id]);

        $notification = new ForumReplyReceived($rootPost);
        $url = $notification->toArray($rootAuthor)['action_url'];

        $otherCycle = Cycle::factory()->create();
        $otherGrade = SchoolGrade::factory()->create(['cycle_id' => $otherCycle->id]);
        $intruder = User::factory()->create(['school_grade_id' => $otherGrade->id])->assignRole('student');

        $this->actingAs($intruder)->get($url)->assertForbidden();
    }
}
