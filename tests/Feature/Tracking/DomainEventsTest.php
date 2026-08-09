<?php

namespace Tests\Feature\Tracking;

use App\Models\User;
use App\Modules\Assessment\Actions\EvaluateSubmissionAction;
use App\Modules\Assessment\Actions\RegisterSubmissionAction;
use App\Modules\Assessment\Events\SubmissionEvaluated;
use App\Modules\Assessment\Events\SubmissionRegistered;
use App\Modules\Assessment\Models\Rubric;
use App\Modules\Assessment\Models\RubricCriterion;
use App\Modules\Community\Actions\CreateForumPostAction;
use App\Modules\Community\Actions\SendChatMessageAction;
use App\Modules\Community\Actions\ToggleForumPostLikeAction;
use App\Modules\Community\Events\ChatMessageSent;
use App\Modules\Community\Events\ForumPostCreated;
use App\Modules\Community\Events\ForumPostLiked;
use App\Modules\Community\Events\ForumPostUnliked;
use App\Modules\Community\Models\ForumPost;
use App\Modules\Community\Models\ForumThread;
use App\Modules\Institution\Models\Group;
use App\Modules\Project\Models\ExpectedEvidence;
use App\Modules\Project\Models\Project;
use App\Modules\Tracking\Jobs\RecalculateStudentProgressJob;
use App\Modules\Tracking\Models\LearningEvent;
use Database\Seeders\RoleLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RubricLevelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Hito 4, trabajo previo obligatorio: las 5 Actions ya existentes disparan
 * eventos de dominio sin cambiar su comportamiento (eso ya lo confirmó el
 * checkpoint de la suite completa, 279/279). Aquí se fija el contrato
 * exacto de cada evento, y que sus Listeners (registrados en
 * AppServiceProvider) de verdad escriben en learning_events y encolan el
 * recálculo -- sin mockear el Listener, dejándolo correr de verdad.
 */
class DomainEventsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLevelSeeder::class);
        $this->seed(RubricLevelSeeder::class);
    }

    public function test_evaluate_submission_action_fires_submission_evaluated_without_changing_behavior(): void
    {
        // Event::fake() sin argumentos también silencia los Observers de
        // Eloquent (ProjectObserver crea las 4 fases vía el evento interno
        // 'eloquent.created') -- se acota a los eventos que de verdad se
        // están probando aquí.
        Event::fake([SubmissionEvaluated::class, SubmissionRegistered::class]);

        [$teacher, $student, $submission] = $this->makeSubmission();
        $rubric = Rubric::factory()->create();
        $criterion = RubricCriterion::factory()->for($rubric)->create();
        $level = \App\Modules\Assessment\Models\RubricLevel::where('key', 'logro_esperado')->firstOrFail();

        $evaluation = app(EvaluateSubmissionAction::class)->execute(
            $teacher, $submission, [$criterion->id => $level->id], 'Buen trabajo',
        );

        $this->assertSame('evaluated', $submission->fresh()->status);
        $this->assertSame('Buen trabajo', $evaluation->feedback);

        Event::assertDispatched(SubmissionEvaluated::class, fn ($event) => $event->evaluation->is($evaluation));
    }

    public function test_register_submission_action_fires_submission_registered_and_returns_the_submission(): void
    {
        Event::fake([SubmissionRegistered::class]);

        $project = Project::factory()->create();
        $phase = $project->phases()->first();
        $evidence = ExpectedEvidence::factory()->create(['phase_id' => $phase->id]);
        $student = User::factory()->create()->assignRole('student');

        $submission = app(RegisterSubmissionAction::class)->execute($evidence, $student, ['text_content' => 'Mi entrega']);

        $this->assertSame('Mi entrega', $submission->text_content);
        Event::assertDispatched(SubmissionRegistered::class, fn ($event) => $event->submission->is($submission));
    }

    public function test_create_forum_post_action_fires_forum_post_created(): void
    {
        Event::fake([ForumPostCreated::class]);

        $thread = ForumThread::factory()->create();
        $author = User::factory()->create()->assignRole('student');

        $post = app(CreateForumPostAction::class)->execute($thread, $author, ['content' => 'Hola']);

        Event::assertDispatched(ForumPostCreated::class, fn ($event) => $event->post->is($post));
    }

    public function test_send_chat_message_action_fires_chat_message_sent(): void
    {
        Event::fake([ChatMessageSent::class]);

        $group = Group::factory()->create();
        $author = User::factory()->create()->assignRole('student');

        $message = app(SendChatMessageAction::class)->execute($group, $author, 'Hola grupo');

        Event::assertDispatched(ChatMessageSent::class, fn ($event) => $event->message->is($message));
    }

    public function test_toggle_like_action_fires_liked_then_unliked(): void
    {
        Event::fake([ForumPostLiked::class, ForumPostUnliked::class]);

        $post = ForumPost::factory()->create();
        $user = User::factory()->create()->assignRole('student');

        $liked = app(ToggleForumPostLikeAction::class)->execute($post, $user);
        $this->assertTrue($liked);
        Event::assertDispatched(ForumPostLiked::class, fn ($event) => $event->post->is($post) && $event->user->is($user));

        $unliked = app(ToggleForumPostLikeAction::class)->execute($post, $user);
        $this->assertFalse($unliked);
        Event::assertDispatched(ForumPostUnliked::class, fn ($event) => $event->post->is($post) && $event->user->is($user));
    }

    /**
     * Sin Event::fake() aquí a propósito: se deja correr el Listener real
     * (registrado en AppServiceProvider) para confirmar que escribe
     * learning_events y encola el job de recálculo.
     */
    public function test_forum_post_created_listener_writes_learning_event_and_queues_recalculation(): void
    {
        Queue::fake();

        $project = Project::factory()->create();
        $thread = ForumThread::factory()->create(['project_id' => $project->id]);
        $author = User::factory()->create()->assignRole('student');

        $post = app(CreateForumPostAction::class)->execute($thread, $author, ['content' => 'Hola']);

        $this->assertDatabaseHas('learning_events', [
            'student_id' => $author->id,
            'project_id' => $project->id,
            'event_type' => 'forum_post_created',
        ]);

        Queue::assertPushed(RecalculateStudentProgressJob::class, fn ($job) => $job->student->is($author) && $job->project->is($project));

        $event = LearningEvent::where('event_type', 'forum_post_created')->first();
        $this->assertSame($post->id, $event->payload['forum_post_id']);
    }

    public function test_chat_message_sent_listener_has_null_project_in_learning_event_but_queues_all_of_the_students_projects(): void
    {
        Queue::fake();

        $cycle = \App\Modules\Institution\Models\Cycle::factory()->create();
        $grade = \App\Modules\Institution\Models\SchoolGrade::factory()->create(['cycle_id' => $cycle->id]);
        $group = Group::factory()->create(['cycle_id' => $cycle->id]);
        $student = User::factory()->create(['group_id' => $group->id, 'school_grade_id' => $grade->id])->assignRole('student');

        $projectA = Project::factory()->create(['cycle_id' => $cycle->id]);
        $projectB = Project::factory()->create(['cycle_id' => $cycle->id]);
        $otherCycleProject = Project::factory()->create();

        app(SendChatMessageAction::class)->execute($group, $student, 'Hola');

        $this->assertDatabaseHas('learning_events', [
            'student_id' => $student->id,
            'project_id' => null,
            'event_type' => 'chat_message_sent',
        ]);

        Queue::assertPushed(RecalculateStudentProgressJob::class, fn ($job) => $job->student->is($student) && $job->project->is($projectA));
        Queue::assertPushed(RecalculateStudentProgressJob::class, fn ($job) => $job->student->is($student) && $job->project->is($projectB));
        Queue::assertNotPushed(RecalculateStudentProgressJob::class, fn ($job) => $job->project->is($otherCycleProject));
    }

    /**
     * @return array{0: User, 1: User, 2: \App\Modules\Assessment\Models\Submission}
     */
    private function makeSubmission(): array
    {
        $teacher = User::factory()->create()->assignRole('teacher');
        $project = Project::factory()->create(['created_by_user_id' => $teacher->id]);
        $phase = $project->phases()->first();
        $rubric = Rubric::factory()->create();
        $evidence = ExpectedEvidence::factory()->create(['phase_id' => $phase->id, 'rubric_id' => $rubric->id]);
        $student = User::factory()->create()->assignRole('student');

        $submission = app(RegisterSubmissionAction::class)->execute($evidence, $student, ['text_content' => 'Entrega']);

        return [$teacher, $student, $submission];
    }
}
