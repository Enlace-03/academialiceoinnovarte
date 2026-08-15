<?php

namespace Tests\Feature\Community;

use App\Models\User;
use App\Modules\Community\Models\PrivateChatThread;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectTeam;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivateChatThreadPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_the_students_own_individual_thread_is_visible_and_writable(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');
        $student = User::factory()->create()->assignRole('student');
        $project = Project::factory()->create(['created_by_user_id' => $teacher->id]);
        $thread = PrivateChatThread::factory()->create(['project_id' => $project->id, 'student_id' => $student->id]);

        $this->assertTrue($student->can('view', $thread));
        $this->assertTrue($student->can('create', [PrivateChatThread::class, $project, 'individual', $student, null]));
    }

    public function test_a_different_student_cannot_view_or_write_in_someone_elses_individual_thread(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');
        $student = User::factory()->create()->assignRole('student');
        $otherStudent = User::factory()->create()->assignRole('student');
        $project = Project::factory()->create(['created_by_user_id' => $teacher->id]);
        $thread = PrivateChatThread::factory()->create(['project_id' => $project->id, 'student_id' => $student->id]);

        $this->assertFalse($otherStudent->can('view', $thread));
        $this->assertFalse($otherStudent->can('create', [PrivateChatThread::class, $project, 'individual', $student, null]));
    }

    public function test_team_member_can_view_and_write_in_the_team_thread(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');
        $member = User::factory()->create()->assignRole('student');
        $project = Project::factory()->create(['created_by_user_id' => $teacher->id]);
        $team = ProjectTeam::factory()->create(['project_id' => $project->id]);
        $team->users()->attach($member->id);
        $thread = PrivateChatThread::factory()->team()->create(['project_id' => $project->id, 'team_id' => $team->id]);

        $this->assertTrue($member->can('view', $thread));
        $this->assertTrue($member->can('create', [PrivateChatThread::class, $project, 'team', null, $team]));
    }

    public function test_a_student_outside_the_team_cannot_view_or_write_in_the_team_thread(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');
        $outsider = User::factory()->create()->assignRole('student');
        $project = Project::factory()->create(['created_by_user_id' => $teacher->id]);
        $team = ProjectTeam::factory()->create(['project_id' => $project->id]);
        $thread = PrivateChatThread::factory()->team()->create(['project_id' => $project->id, 'team_id' => $team->id]);

        $this->assertFalse($outsider->can('view', $thread));
        $this->assertFalse($outsider->can('create', [PrivateChatThread::class, $project, 'team', null, $team]));
    }

    public function test_the_responsible_teacher_can_view_and_write_in_any_thread_of_their_own_project(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');
        $student = User::factory()->create()->assignRole('student');
        $project = Project::factory()->create(['created_by_user_id' => $teacher->id]);
        $thread = PrivateChatThread::factory()->create(['project_id' => $project->id, 'student_id' => $student->id]);

        $this->assertTrue($teacher->can('view', $thread));
        $this->assertTrue($teacher->can('create', [PrivateChatThread::class, $project, 'individual', $student, null]));
    }

    public function test_a_teacher_without_authority_over_the_project_cannot_view_or_write(): void
    {
        $owner = User::factory()->create()->assignRole('teacher');
        $otherTeacher = User::factory()->create()->assignRole('teacher');
        $student = User::factory()->create()->assignRole('student');
        $project = Project::factory()->create(['created_by_user_id' => $owner->id]);
        $thread = PrivateChatThread::factory()->create(['project_id' => $project->id, 'student_id' => $student->id]);

        $this->assertFalse($otherTeacher->can('view', $thread));
        $this->assertFalse($otherTeacher->can('create', [PrivateChatThread::class, $project, 'individual', $student, null]));
    }

    /**
     * El caso central del pedido: coordinator/rector con SOLO
     * private_chats.view.all (sin projects.update.*) pueden leer pero
     * nunca escribir.
     */
    public function test_institutional_viewer_with_only_view_all_can_read_but_not_write(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');
        $student = User::factory()->create()->assignRole('student');
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('private_chats.view.all');
        $project = Project::factory()->create(['created_by_user_id' => $teacher->id]);
        $thread = PrivateChatThread::factory()->create(['project_id' => $project->id, 'student_id' => $student->id]);

        $this->assertTrue($viewer->can('view', $thread));
        $this->assertFalse($viewer->can('create', [PrivateChatThread::class, $project, 'individual', $student, null]));
    }

    /**
     * Un coordinator/rector real (preset completo) SÍ tiene
     * projects.update.all además de private_chats.view.all -- por eso
     * también puede escribir, vía autoridad real sobre el proyecto, no vía
     * la visibilidad institucional. Confirma que la exclusión de arriba es
     * específica de .view.all sola, no de todo el rol.
     */
    public function test_coordinator_with_real_project_authority_can_also_write(): void
    {
        $coordinator = User::factory()->create()->assignRole('coordinator');
        $student = User::factory()->create()->assignRole('student');
        $teacher = User::factory()->create()->assignRole('teacher');
        $project = Project::factory()->create(['created_by_user_id' => $teacher->id]);

        $this->assertTrue($coordinator->can('create', [PrivateChatThread::class, $project, 'individual', $student, null]));
    }

    public function test_moderate_is_restricted_to_users_with_the_moderate_permission(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');
        $student = User::factory()->create()->assignRole('student');
        $moderator = User::factory()->create();
        $moderator->givePermissionTo('private_chats.moderate');
        $project = Project::factory()->create(['created_by_user_id' => $teacher->id]);
        $thread = PrivateChatThread::factory()->create(['project_id' => $project->id, 'student_id' => $student->id]);

        $this->assertTrue($moderator->can('moderate', $thread));
        $this->assertFalse($teacher->can('moderate', $thread));
        $this->assertFalse($student->can('moderate', $thread));
    }
}
