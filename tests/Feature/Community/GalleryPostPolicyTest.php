<?php

namespace Tests\Feature\Community;

use App\Models\User;
use App\Modules\Community\Models\GalleryPost;
use App\Modules\Institution\Models\Cycle;
use App\Modules\Institution\Models\SchoolGrade;
use App\Modules\Project\Models\Project;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GalleryPostPolicyTest extends TestCase
{
    use RefreshDatabase;

    private Project $ownCycleProject;

    private Project $otherCycleProject;

    private User $studentOwnCycle;

    private User $parentOfOwnCycleStudent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $ownCycle = Cycle::factory()->create();
        $otherCycle = Cycle::factory()->create();

        $ownGrade = SchoolGrade::factory()->create(['cycle_id' => $ownCycle->id]);

        $this->ownCycleProject = Project::factory()->create(['cycle_id' => $ownCycle->id]);
        $this->otherCycleProject = Project::factory()->create(['cycle_id' => $otherCycle->id]);

        $this->studentOwnCycle = User::factory()->create(['school_grade_id' => $ownGrade->id])
            ->assignRole('student');

        $this->parentOfOwnCycleStudent = User::factory()->create()->assignRole('parent');
        $this->parentOfOwnCycleStudent->children()->attach($this->studentOwnCycle->id, ['relationship' => 'madre']);
    }

    public function test_student_can_view_general_gallery_post(): void
    {
        $post = GalleryPost::factory()->create(['project_id' => null]);

        $this->assertTrue($this->studentOwnCycle->can('view', $post));
    }

    public function test_student_can_view_post_of_own_cycle_project(): void
    {
        $post = GalleryPost::factory()->create(['project_id' => $this->ownCycleProject->id]);

        $this->assertTrue($this->studentOwnCycle->can('view', $post));
    }

    public function test_student_cannot_view_post_of_other_cycle_project(): void
    {
        $post = GalleryPost::factory()->create(['project_id' => $this->otherCycleProject->id]);

        $this->assertFalse($this->studentOwnCycle->can('view', $post));
    }

    public function test_parent_can_view_general_gallery_post(): void
    {
        $post = GalleryPost::factory()->create(['project_id' => null]);

        $this->assertTrue($this->parentOfOwnCycleStudent->can('view', $post));
    }

    public function test_parent_can_view_post_of_their_childs_cycle_project(): void
    {
        $post = GalleryPost::factory()->create(['project_id' => $this->ownCycleProject->id]);

        $this->assertTrue($this->parentOfOwnCycleStudent->can('view', $post));
    }

    public function test_parent_cannot_view_post_of_other_cycle_project(): void
    {
        $post = GalleryPost::factory()->create(['project_id' => $this->otherCycleProject->id]);

        $this->assertFalse($this->parentOfOwnCycleStudent->can('view', $post));
    }

    public function test_teacher_with_gallery_publish_can_create(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');

        $this->assertTrue($teacher->can('create', GalleryPost::class));
    }

    public function test_student_cannot_create_gallery_post(): void
    {
        $this->assertFalse($this->studentOwnCycle->can('create', GalleryPost::class));
    }

    public function test_teacher_can_update_own_gallery_post(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');
        $post = GalleryPost::factory()->create(['created_by_user_id' => $teacher->id]);

        $this->assertTrue($teacher->can('update', $post));
    }

    public function test_teacher_cannot_update_another_teachers_gallery_post(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');
        $otherTeacher = User::factory()->create()->assignRole('teacher');
        $post = GalleryPost::factory()->create(['created_by_user_id' => $otherTeacher->id]);

        $this->assertFalse($teacher->can('update', $post));
    }

    public function test_rector_can_update_any_gallery_post(): void
    {
        $rector = User::factory()->create()->assignRole('rector');
        $teacher = User::factory()->create()->assignRole('teacher');
        $post = GalleryPost::factory()->create(['created_by_user_id' => $teacher->id]);

        $this->assertTrue($rector->can('update', $post));
    }

    public function test_coordinator_can_update_any_gallery_post(): void
    {
        $coordinator = User::factory()->create()->assignRole('coordinator');
        $teacher = User::factory()->create()->assignRole('teacher');
        $post = GalleryPost::factory()->create(['created_by_user_id' => $teacher->id]);

        $this->assertTrue($coordinator->can('update', $post));
    }

    public function test_teacher_can_delete_own_gallery_post(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');
        $post = GalleryPost::factory()->create(['created_by_user_id' => $teacher->id]);

        $this->assertTrue($teacher->can('delete', $post));
    }
}
