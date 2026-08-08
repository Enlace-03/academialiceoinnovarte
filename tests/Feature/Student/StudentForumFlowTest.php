<?php

namespace Tests\Feature\Student;

use App\Livewire\Student\ForumThreadList;
use App\Livewire\Student\ForumThreadShow;
use App\Models\User;
use App\Modules\Community\Models\ForumPost;
use App\Modules\Community\Models\ForumThread;
use App\Modules\Institution\Models\Cycle;
use App\Modules\Institution\Models\SchoolGrade;
use App\Modules\Project\Models\Project;
use Database\Seeders\RoleLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Flujo del portal de estudiante sobre foro (Hito 3b-1): crear hilo,
 * publicar, responder (un solo nivel), dar like. Aislamiento cruzado ya está
 * cubierto exhaustivamente a nivel de Policy en ForumThreadPolicyTest /
 * ForumPostPolicyTest -- aquí se verifica que la ruta real (con el chequeo
 * defensivo de que {project}/{thread} coincidan) respeta lo mismo.
 */
class StudentForumFlowTest extends TestCase
{
    use RefreshDatabase;

    private Cycle $cycle;

    private User $student;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLevelSeeder::class);

        $this->cycle = Cycle::factory()->create();
        $grade = SchoolGrade::factory()->create(['cycle_id' => $this->cycle->id]);
        $this->student = User::factory()->create(['school_grade_id' => $grade->id])->assignRole('student');
        $this->project = Project::factory()->create(['cycle_id' => $this->cycle->id]);
    }

    public function test_student_can_create_a_thread_and_is_redirected_to_it(): void
    {
        $this->actingAs($this->student);

        Livewire::test(ForumThreadList::class, ['project' => $this->project])
            ->set('newThreadTitle', 'Dudas sobre la fase 1')
            ->call('createThread')
            ->assertRedirect();

        $this->assertDatabaseHas('forum_threads', [
            'project_id' => $this->project->id,
            'title' => 'Dudas sobre la fase 1',
            'created_by' => $this->student->id,
        ]);
    }

    public function test_student_can_post_reply_and_like_in_a_thread(): void
    {
        $thread = ForumThread::factory()->create(['project_id' => $this->project->id]);

        $this->actingAs($this->student);

        $component = Livewire::test(ForumThreadShow::class, ['project' => $this->project, 'thread' => $thread])
            ->set('newPostContent', 'Hola a todos')
            ->call('createPost');

        $post = ForumPost::where('forum_thread_id', $thread->id)->firstOrFail();
        $this->assertSame('Hola a todos', $post->content);

        $component->call('startReply', $post->id)
            ->set('replyContent', 'Gracias por compartir')
            ->call('submitReply');

        $this->assertDatabaseHas('forum_posts', [
            'parent_post_id' => $post->id,
            'content' => 'Gracias por compartir',
        ]);

        $component->call('toggleLike', $post->id);

        $this->assertSame(1, $post->likes()->count());
    }

    public function test_student_cannot_reply_to_a_reply(): void
    {
        $thread = ForumThread::factory()->create(['project_id' => $this->project->id]);
        $rootPost = ForumPost::factory()->create(['forum_thread_id' => $thread->id]);
        $existingReply = ForumPost::factory()->create([
            'forum_thread_id' => $thread->id,
            'parent_post_id' => $rootPost->id,
        ]);

        $this->actingAs($this->student);

        Livewire::test(ForumThreadShow::class, ['project' => $this->project, 'thread' => $thread])
            ->set('replyingToPostId', $existingReply->id)
            ->set('replyContent', 'Intento inválido')
            ->call('submitReply')
            ->assertHasErrors('parent_post_id');

        $this->assertDatabaseMissing('forum_posts', ['content' => 'Intento inválido']);
    }

    public function test_cross_cycle_student_cannot_view_thread_via_direct_url(): void
    {
        $thread = ForumThread::factory()->create(['project_id' => $this->project->id]);

        $otherCycle = Cycle::factory()->create();
        $otherGrade = SchoolGrade::factory()->create(['cycle_id' => $otherCycle->id]);
        $otherStudent = User::factory()->create(['school_grade_id' => $otherGrade->id])->assignRole('student');

        $this->actingAs($otherStudent)
            ->get(route('student.forum.show', ['project' => $this->project->uuid, 'thread' => $thread->uuid]))
            ->assertForbidden();
    }

    /**
     * Defensa en profundidad: el middleware role:student bloquea ANTES de
     * que se evalúe ForumThreadPolicy -- un teacher/parent recibe 403 aquí
     * aunque nunca lleguen a montar ForumThreadShow.
     */
    public function test_teacher_cannot_access_forum_show_route_middleware(): void
    {
        $thread = ForumThread::factory()->create(['project_id' => $this->project->id]);
        $teacher = User::factory()->create()->assignRole('teacher');

        $this->actingAs($teacher)
            ->get(route('student.forum.show', ['project' => $this->project->uuid, 'thread' => $thread->uuid]))
            ->assertForbidden();
    }

    public function test_parent_cannot_access_forum_show_route_middleware(): void
    {
        $thread = ForumThread::factory()->create(['project_id' => $this->project->id]);
        $parent = User::factory()->create()->assignRole('parent');

        $this->actingAs($parent)
            ->get(route('student.forum.show', ['project' => $this->project->uuid, 'thread' => $thread->uuid]))
            ->assertForbidden();
    }

    public function test_mismatched_project_and_thread_in_url_returns_404(): void
    {
        $otherProject = Project::factory()->create(['cycle_id' => $this->cycle->id]);
        $thread = ForumThread::factory()->create(['project_id' => $this->project->id]);

        $this->actingAs($this->student)
            ->get(route('student.forum.show', ['project' => $otherProject->uuid, 'thread' => $thread->uuid]))
            ->assertNotFound();
    }

    public function test_student_never_sees_who_liked_a_post_only_the_count(): void
    {
        $thread = ForumThread::factory()->create(['project_id' => $this->project->id]);
        $post = ForumPost::factory()->create(['forum_thread_id' => $thread->id]);
        $liker = User::factory()->create(['name' => 'Liker Secreto']);
        $post->likes()->attach($liker->id);

        $response = $this->actingAs($this->student)
            ->get(route('student.forum.show', ['project' => $this->project->uuid, 'thread' => $thread->uuid]));

        $response->assertOk();
        $response->assertDontSee('Liker Secreto');
    }
}
