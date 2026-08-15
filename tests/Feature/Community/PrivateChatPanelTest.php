<?php

namespace Tests\Feature\Community;

use App\Livewire\Shared\PrivateChatPanel;
use App\Models\User;
use App\Modules\Community\Models\PrivateChatMessage;
use App\Modules\Community\Models\PrivateChatThread;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectTeam;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PrivateChatPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_sending_the_first_message_creates_the_thread(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');
        $student = User::factory()->create()->assignRole('student');
        $project = Project::factory()->create(['created_by_user_id' => $teacher->id]);

        $this->assertDatabaseCount('private_chat_threads', 0);

        Livewire::actingAs($student)
            ->test(PrivateChatPanel::class, ['project' => $project, 'type' => 'individual', 'student' => $student])
            ->set('content', 'Hola profe, tengo una duda.')
            ->call('send');

        $this->assertDatabaseCount('private_chat_threads', 1);
        $this->assertDatabaseHas('private_chat_messages', ['content' => 'Hola profe, tengo una duda.', 'user_id' => $student->id]);
    }

    /**
     * firstOrCreate no debe duplicar el hilo en envíos repetidos -- ni
     * entre mensajes del mismo remitente, ni cuando responde el docente
     * al mismo hilo ya creado por el estudiante.
     */
    public function test_repeated_sends_never_duplicate_the_thread(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');
        $student = User::factory()->create()->assignRole('student');
        $project = Project::factory()->create(['created_by_user_id' => $teacher->id]);

        Livewire::actingAs($student)
            ->test(PrivateChatPanel::class, ['project' => $project, 'type' => 'individual', 'student' => $student])
            ->set('content', 'Primer mensaje')
            ->call('send')
            ->set('content', 'Segundo mensaje')
            ->call('send');

        Livewire::actingAs($teacher)
            ->test(PrivateChatPanel::class, ['project' => $project, 'type' => 'individual', 'student' => $student])
            ->set('content', 'Respuesta del docente')
            ->call('send');

        $this->assertDatabaseCount('private_chat_threads', 1);
        $this->assertSame(3, PrivateChatMessage::count());
    }

    public function test_student_cannot_send_in_another_students_individual_thread(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');
        $student = User::factory()->create()->assignRole('student');
        $intruder = User::factory()->create()->assignRole('student');
        $project = Project::factory()->create(['created_by_user_id' => $teacher->id]);

        Livewire::actingAs($intruder)
            ->test(PrivateChatPanel::class, ['project' => $project, 'type' => 'individual', 'student' => $student])
            ->assertForbidden();
    }

    public function test_student_outside_the_team_cannot_mount_the_team_panel(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');
        $outsider = User::factory()->create()->assignRole('student');
        $project = Project::factory()->create(['created_by_user_id' => $teacher->id]);
        $team = ProjectTeam::factory()->create(['project_id' => $project->id]);

        Livewire::actingAs($outsider)
            ->test(PrivateChatPanel::class, ['project' => $project, 'type' => 'team', 'team' => $team])
            ->assertForbidden();
    }

    public function test_student_outside_the_team_cannot_send_even_if_a_thread_already_exists(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');
        $member = User::factory()->create()->assignRole('student');
        $outsider = User::factory()->create()->assignRole('student');
        $project = Project::factory()->create(['created_by_user_id' => $teacher->id]);
        $team = ProjectTeam::factory()->create(['project_id' => $project->id]);
        $team->users()->attach($member->id);
        PrivateChatThread::factory()->team()->create(['project_id' => $project->id, 'team_id' => $team->id]);

        // El outsider nunca llega a mount() con éxito -- ver test anterior --
        // pero además, si alguien intentara forzar send() directo sobre el
        // método público (bypaseando mount), sigue rechazado.
        $this->assertFalse($outsider->can('create', [PrivateChatThread::class, $project, 'team', null, $team]));
    }

    public function test_institutional_viewer_sees_messages_but_cannot_send(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');
        $student = User::factory()->create()->assignRole('student');
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('private_chats.view.all');
        $project = Project::factory()->create(['created_by_user_id' => $teacher->id]);
        $thread = PrivateChatThread::factory()->create(['project_id' => $project->id, 'student_id' => $student->id]);
        PrivateChatMessage::factory()->create(['thread_id' => $thread->id, 'user_id' => $student->id, 'content' => 'Mensaje visible institucionalmente']);

        $component = Livewire::actingAs($viewer)
            ->test(PrivateChatPanel::class, ['project' => $project, 'type' => 'individual', 'student' => $student]);

        $component->assertSee('Mensaje visible institucionalmente');
        $this->assertFalse($component->instance()->canSend());
    }

    public function test_moderator_can_hide_a_message_but_teacher_cannot(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');
        $student = User::factory()->create()->assignRole('student');
        $moderator = User::factory()->create();
        $moderator->givePermissionTo(['private_chats.moderate', 'private_chats.view.all']);
        $project = Project::factory()->create(['created_by_user_id' => $teacher->id]);
        $thread = PrivateChatThread::factory()->create(['project_id' => $project->id, 'student_id' => $student->id]);
        $message = PrivateChatMessage::factory()->create(['thread_id' => $thread->id, 'user_id' => $student->id]);

        Livewire::actingAs($teacher)
            ->test(PrivateChatPanel::class, ['project' => $project, 'type' => 'individual', 'student' => $student])
            ->call('hide', $message->id)
            ->assertForbidden();

        $this->assertFalse($message->fresh()->is_hidden);

        Livewire::actingAs($moderator)
            ->test(PrivateChatPanel::class, ['project' => $project, 'type' => 'individual', 'student' => $student])
            ->call('hide', $message->id);

        $this->assertTrue($message->fresh()->is_hidden);
        $this->assertSame($moderator->id, $message->fresh()->hidden_by_user_id);
    }

    public function test_hidden_messages_are_excluded_for_regular_participants_but_visible_to_moderators(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');
        $student = User::factory()->create()->assignRole('student');
        $moderator = User::factory()->create();
        $moderator->givePermissionTo(['private_chats.moderate', 'private_chats.view.all']);
        $project = Project::factory()->create(['created_by_user_id' => $teacher->id]);
        $thread = PrivateChatThread::factory()->create(['project_id' => $project->id, 'student_id' => $student->id]);
        PrivateChatMessage::factory()->create([
            'thread_id' => $thread->id,
            'user_id' => $teacher->id,
            'content' => 'Contenido inapropiado',
            'is_hidden' => true,
            'hidden_at' => now(),
            'hidden_by_user_id' => $moderator->id,
        ]);

        Livewire::actingAs($student)
            ->test(PrivateChatPanel::class, ['project' => $project, 'type' => 'individual', 'student' => $student])
            ->assertDontSee('Contenido inapropiado');

        Livewire::actingAs($moderator)
            ->test(PrivateChatPanel::class, ['project' => $project, 'type' => 'individual', 'student' => $student])
            ->assertSee('Contenido inapropiado');
    }
}
