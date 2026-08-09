<?php

namespace Tests\Feature\Communication;

use App\Models\User;
use App\Modules\Assessment\Actions\EvaluateSubmissionAction;
use App\Modules\Assessment\Models\Evaluation;
use App\Modules\Assessment\Models\RubricCriterion;
use App\Modules\Assessment\Models\RubricLevel;
use App\Modules\Assessment\Models\Submission;
use App\Modules\Communication\Notifications\EvaluationReceived;
use App\Modules\Communication\Notifications\ForumReplyReceived;
use App\Modules\Community\Actions\CreateForumPostAction;
use App\Modules\Community\Models\ForumPost;
use App\Modules\Community\Models\ForumThread;
use App\Modules\Institution\Models\Cycle;
use App\Modules\Institution\Models\Group;
use App\Modules\Institution\Models\Institution;
use App\Modules\Institution\Models\SchoolGrade;
use App\Modules\Project\Models\ExpectedEvidence;
use App\Modules\Project\Models\Project;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RubricLevelSeeder;
use Filament\Notifications\DatabaseNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Aislamiento del módulo de notificaciones (segunda vuelta del Hito 5).
 * Reutiliza el mismo patrón de fixtures (ownCycle/otherCycle, docente con/sin
 * autoridad de proyecto) ya establecido en ForumThreadPolicyTest y
 * StudentForumFlowTest -- las Policies de acceso ya están cubiertas ahí, esto
 * cubre que el módulo de notificaciones respete exactamente el mismo límite,
 * no que lo reimplemente.
 */
class NotificationIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RubricLevelSeeder::class);
        Institution::factory()->create();
    }

    public function test_a_student_in_another_cycle_never_receives_any_notification_from_own_cycle_forum_activity(): void
    {
        $ownCycle = Cycle::factory()->create();
        $ownGrade = SchoolGrade::factory()->create(['cycle_id' => $ownCycle->id]);
        $rootAuthor = User::factory()->create(['school_grade_id' => $ownGrade->id])->assignRole('student');
        $replier = User::factory()->create(['school_grade_id' => $ownGrade->id])->assignRole('student');

        $otherCycle = Cycle::factory()->create();
        $otherGrade = SchoolGrade::factory()->create(['cycle_id' => $otherCycle->id]);
        $unrelatedStudent = User::factory()->create(['school_grade_id' => $otherGrade->id])->assignRole('student');

        $project = Project::factory()->create(['cycle_id' => $ownCycle->id]);
        $thread = ForumThread::factory()->create(['project_id' => $project->id]);
        $rootPost = ForumPost::factory()->create(['forum_thread_id' => $thread->id, 'user_id' => $rootAuthor->id]);

        Notification::fake();

        app(CreateForumPostAction::class)->execute($thread, $replier, [
            'content' => 'respuesta dentro del mismo ciclo',
            'parent_post_id' => $rootPost->id,
        ]);

        Notification::assertSentTo($rootAuthor, ForumReplyReceived::class);
        Notification::assertNothingSentTo($unrelatedStudent);
    }

    public function test_a_teacher_without_project_authority_never_receives_notification_for_that_project(): void
    {
        $teacherWithAuthority = User::factory()->create()->assignRole('teacher');
        $unrelatedTeacher = User::factory()->create()->assignRole('teacher');
        $project = Project::factory()->create(['created_by_user_id' => $teacherWithAuthority->id]);
        $thread = ForumThread::factory()->create(['project_id' => $project->id]);
        $student = User::factory()->create()->assignRole('student');

        Notification::fake();

        app(CreateForumPostAction::class)->execute($thread, $student, ['content' => 'pregunta']);

        Notification::assertSentTo($teacherWithAuthority, DatabaseNotification::class);
        Notification::assertNothingSentTo($unrelatedTeacher);
    }

    public function test_notification_content_shows_the_correct_name_and_group_of_the_referenced_student(): void
    {
        $cycle = Cycle::factory()->create(['name' => 'Conceptual']);
        // Group::name ya es "Ciclo - Sección" por diseño (InstitutionSeeder),
        // no solo la sección -- ver docblock de FormatsStudentLabel.
        $group = Group::factory()->create(['cycle_id' => $cycle->id, 'name' => 'Conceptual - A']);
        $grade = SchoolGrade::factory()->create(['cycle_id' => $cycle->id]);
        $rootAuthor = User::factory()->create(['school_grade_id' => $grade->id])->assignRole('student');
        $replier = User::factory()->create([
            'name' => 'Maria Fernanda Lopez',
            'school_grade_id' => $grade->id,
            'group_id' => $group->id,
        ])->assignRole('student');

        $project = Project::factory()->create(['cycle_id' => $cycle->id]);
        $thread = ForumThread::factory()->create(['project_id' => $project->id]);
        $rootPost = ForumPost::factory()->create(['forum_thread_id' => $thread->id, 'user_id' => $rootAuthor->id]);

        Notification::fake();

        app(CreateForumPostAction::class)->execute($thread, $replier, [
            'content' => 'respuesta',
            'parent_post_id' => $rootPost->id,
        ]);

        Notification::assertSentTo($rootAuthor, ForumReplyReceived::class, function (ForumReplyReceived $notification) use ($rootAuthor) {
            return $notification->toArray($rootAuthor)['author_name'] === 'Maria Fernanda Lopez (Conceptual - A)';
        });
    }

    public function test_evaluation_received_respects_the_same_cycle_routing_as_deadline_reminders(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');
        $project = Project::factory()->create(['created_by_user_id' => $teacher->id]);
        $phase = $project->phases()->first();
        $evidence = ExpectedEvidence::factory()->create(['phase_id' => $phase->id]);

        $directCycle = Cycle::factory()->create(['order' => 3]);
        $directGrade = SchoolGrade::factory()->create(['cycle_id' => $directCycle->id]);
        $directStudent = User::factory()->create(['school_grade_id' => $directGrade->id])->assignRole('student');

        $guardianCycle = Cycle::factory()->create(['order' => 1]);
        $guardianGrade = SchoolGrade::factory()->create(['cycle_id' => $guardianCycle->id]);
        $routedStudent = User::factory()->create(['school_grade_id' => $guardianGrade->id])->assignRole('student');
        $guardian = User::factory()->create()->assignRole('parent');
        $guardian->children()->attach($routedStudent->id, ['relationship' => 'madre']);

        $level = RubricLevel::where('key', 'logro_esperado')->firstOrFail();
        $criterion = RubricCriterion::factory()->create();

        Notification::fake();

        foreach ([$directStudent, $routedStudent] as $student) {
            $submission = Submission::factory()->create(['expected_evidence_id' => $evidence->id, 'student_id' => $student->id]);
            app(EvaluateSubmissionAction::class)->execute($teacher, $submission, [$criterion->id => $level->id]);
        }

        Notification::assertSentTo($directStudent, EvaluationReceived::class);
        Notification::assertNotSentTo($routedStudent, EvaluationReceived::class);
        Notification::assertSentTo($guardian, EvaluationReceived::class);

        $notification = new EvaluationReceived(Evaluation::first());
        $this->assertSame(['mail', 'database'], $notification->via($directStudent));
        $this->assertSame(['mail'], $notification->via($guardian));
    }
}
