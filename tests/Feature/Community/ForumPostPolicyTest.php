<?php

namespace Tests\Feature\Community;

use App\Models\User;
use App\Modules\Community\Models\ForumPost;
use App\Modules\Community\Models\ForumThread;
use App\Modules\Institution\Models\Cycle;
use App\Modules\Institution\Models\SchoolGrade;
use App\Modules\Project\Models\Project;
use Database\Seeders\RoleLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForumPostPolicyTest extends TestCase
{
    use RefreshDatabase;

    private Project $ownCycleProject;

    private Project $otherCycleProject;

    private User $studentOwnCycle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLevelSeeder::class);

        $ownCycle = Cycle::factory()->create();
        $otherCycle = Cycle::factory()->create();

        $ownGrade = SchoolGrade::factory()->create(['cycle_id' => $ownCycle->id]);

        $this->ownCycleProject = Project::factory()->create(['cycle_id' => $ownCycle->id]);
        $this->otherCycleProject = Project::factory()->create(['cycle_id' => $otherCycle->id]);

        $this->studentOwnCycle = User::factory()->create(['school_grade_id' => $ownGrade->id])
            ->assignRole('student');
    }

    public function test_student_can_view_post_in_own_cycle_thread(): void
    {
        $thread = ForumThread::factory()->create(['project_id' => $this->ownCycleProject->id]);
        $post = ForumPost::factory()->create(['forum_thread_id' => $thread->id]);

        $this->assertTrue($this->studentOwnCycle->can('view', $post));
    }

    public function test_student_cannot_view_post_in_other_cycle_thread(): void
    {
        $thread = ForumThread::factory()->create(['project_id' => $this->otherCycleProject->id]);
        $post = ForumPost::factory()->create(['forum_thread_id' => $thread->id]);

        $this->assertFalse($this->studentOwnCycle->can('view', $post));
    }

    public function test_student_cannot_view_a_hidden_post(): void
    {
        $thread = ForumThread::factory()->create(['project_id' => $this->ownCycleProject->id]);
        $post = ForumPost::factory()->create(['forum_thread_id' => $thread->id, 'is_hidden' => true]);

        $this->assertFalse($this->studentOwnCycle->can('view', $post));
    }

    public function test_student_cannot_view_a_visible_post_inside_a_hidden_thread(): void
    {
        $thread = ForumThread::factory()->create([
            'project_id' => $this->ownCycleProject->id,
            'is_hidden' => true,
        ]);
        $post = ForumPost::factory()->create(['forum_thread_id' => $thread->id, 'is_hidden' => false]);

        $this->assertFalse($this->studentOwnCycle->can('view', $post));
    }

    public function test_teacher_can_view_a_hidden_post_in_own_project(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');
        $ownProject = Project::factory()->create(['created_by_user_id' => $teacher->id]);
        $thread = ForumThread::factory()->create(['project_id' => $ownProject->id]);
        $post = ForumPost::factory()->create(['forum_thread_id' => $thread->id, 'is_hidden' => true]);

        $this->assertTrue($teacher->can('view', $post));
    }

    public function test_student_can_never_hide_a_post(): void
    {
        $thread = ForumThread::factory()->create(['project_id' => $this->ownCycleProject->id]);
        $post = ForumPost::factory()->create(['forum_thread_id' => $thread->id]);

        $this->assertFalse($this->studentOwnCycle->can('hide', $post));
    }

    public function test_teacher_can_hide_post_in_own_project(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');
        $ownProject = Project::factory()->create(['created_by_user_id' => $teacher->id]);
        $thread = ForumThread::factory()->create(['project_id' => $ownProject->id]);
        $post = ForumPost::factory()->create(['forum_thread_id' => $thread->id]);

        $this->assertTrue($teacher->can('hide', $post));
    }

    public function test_teacher_cannot_hide_post_in_other_teachers_project(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');
        $otherTeacher = User::factory()->create()->assignRole('teacher');
        $otherProject = Project::factory()->create(['created_by_user_id' => $otherTeacher->id]);
        $thread = ForumThread::factory()->create(['project_id' => $otherProject->id]);
        $post = ForumPost::factory()->create(['forum_thread_id' => $thread->id]);

        $this->assertFalse($teacher->can('hide', $post));
    }

    public function test_student_can_like_a_post_in_own_cycle(): void
    {
        $thread = ForumThread::factory()->create(['project_id' => $this->ownCycleProject->id]);
        $post = ForumPost::factory()->create(['forum_thread_id' => $thread->id]);

        $this->assertTrue($this->studentOwnCycle->can('like', $post));
    }

    public function test_student_cannot_like_a_post_in_other_cycle(): void
    {
        $thread = ForumThread::factory()->create(['project_id' => $this->otherCycleProject->id]);
        $post = ForumPost::factory()->create(['forum_thread_id' => $thread->id]);

        $this->assertFalse($this->studentOwnCycle->can('like', $post));
    }

    public function test_student_cannot_view_the_likers_list_of_a_post_they_can_see(): void
    {
        $thread = ForumThread::factory()->create(['project_id' => $this->ownCycleProject->id]);
        $post = ForumPost::factory()->create(['forum_thread_id' => $thread->id]);

        $this->assertTrue($this->studentOwnCycle->can('view', $post));
        $this->assertFalse($this->studentOwnCycle->can('viewLikers', $post));
    }

    public function test_teacher_can_view_the_likers_list_of_a_post_in_own_project(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');
        $ownProject = Project::factory()->create(['created_by_user_id' => $teacher->id]);
        $thread = ForumThread::factory()->create(['project_id' => $ownProject->id]);
        $post = ForumPost::factory()->create(['forum_thread_id' => $thread->id]);

        $this->assertTrue($teacher->can('viewLikers', $post));
    }
}
