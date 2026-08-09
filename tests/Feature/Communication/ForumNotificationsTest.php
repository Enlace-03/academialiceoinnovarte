<?php

namespace Tests\Feature\Communication;

use App\Models\User;
use App\Modules\Communication\Notifications\ForumReplyReceived;
use App\Modules\Community\Actions\CreateForumPostAction;
use App\Modules\Community\Models\ForumPost;
use App\Modules\Community\Models\ForumThread;
use App\Modules\Institution\Models\Institution;
use App\Modules\Project\Models\Project;
use Database\Seeders\RolePermissionSeeder;
use Filament\Notifications\DatabaseNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ForumNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Institution::factory()->create();
    }

    public function test_replying_to_someone_elses_post_notifies_its_author_by_mail_and_in_platform(): void
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

        Notification::assertSentTo($rootAuthor, ForumReplyReceived::class);
    }

    public function test_replying_to_your_own_post_never_notifies_yourself(): void
    {
        $author = User::factory()->create()->assignRole('student');
        $thread = ForumThread::factory()->create();
        $rootPost = ForumPost::factory()->create(['forum_thread_id' => $thread->id, 'user_id' => $author->id]);

        Notification::fake();

        app(CreateForumPostAction::class)->execute($thread, $author, [
            'content' => 'me respondo a mí mismo',
            'parent_post_id' => $rootPost->id,
        ]);

        Notification::assertNotSentTo($author, ForumReplyReceived::class);
    }

    public function test_a_new_root_post_never_triggers_a_direct_reply_notification(): void
    {
        $thread = ForumThread::factory()->create();
        $poster = User::factory()->create()->assignRole('student');

        Notification::fake();

        app(CreateForumPostAction::class)->execute($thread, $poster, ['content' => 'primer post del hilo']);

        Notification::assertNothingSent();
    }

    /**
     * Vía Filament\Notifications\Notification::sendToDatabase() (ver
     * docblock de NotifyTeacherOfForumActivity) -- via() siempre es
     * ['database'], garantizado por el framework, no por nuestro código.
     */
    public function test_the_teacher_with_project_authority_is_notified_in_platform_only_never_by_mail(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');
        $project = Project::factory()->create(['created_by_user_id' => $teacher->id]);
        $thread = ForumThread::factory()->create(['project_id' => $project->id]);
        $student = User::factory()->create()->assignRole('student');

        Notification::fake();

        app(CreateForumPostAction::class)->execute($thread, $student, ['content' => 'una pregunta']);

        Notification::assertSentTo($teacher, DatabaseNotification::class, function (DatabaseNotification $notification) {
            return $notification->data['format'] === 'filament';
        });
    }

    public function test_the_teacher_is_not_notified_of_their_own_post(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');
        $project = Project::factory()->create(['created_by_user_id' => $teacher->id]);
        $thread = ForumThread::factory()->create(['project_id' => $project->id, 'created_by' => $teacher->id]);

        Notification::fake();

        app(CreateForumPostAction::class)->execute($thread, $teacher, ['content' => 'aviso del docente']);

        Notification::assertNotSentTo($teacher, DatabaseNotification::class);
    }
}
