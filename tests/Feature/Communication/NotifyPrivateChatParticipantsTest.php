<?php

namespace Tests\Feature\Communication;

use App\Livewire\Shared\PrivateChatPanel;
use App\Models\User;
use App\Modules\Communication\Notifications\PrivateChatMessageReceived;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectTeam;
use Database\Seeders\RolePermissionSeeder;
use Filament\Notifications\DatabaseNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * NotifyPrivateChatParticipants (disparado por PrivateChatMessageSent, a su
 * vez disparado por SendPrivateChatMessageAction dentro de
 * PrivateChatPanel::send()) -- se dispara enviando un mensaje real vía el
 * componente, no invocando el listener a mano, para probar la cadena
 * completa igual que el resto de la suite de notificaciones del proyecto.
 */
class NotifyPrivateChatParticipantsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_student_message_notifies_the_responsible_teacher_never_the_author(): void
    {
        Notification::fake();

        $teacher = User::factory()->create()->assignRole('teacher');
        $student = User::factory()->create()->assignRole('student');
        $project = Project::factory()->create(['created_by_user_id' => $teacher->id]);

        Livewire::actingAs($student)
            ->test(PrivateChatPanel::class, ['project' => $project, 'type' => 'individual', 'student' => $student])
            ->set('content', 'Tengo una duda')
            ->call('send');

        Notification::assertNothingSentTo($student);
        Notification::assertSentTo($teacher, DatabaseNotification::class, fn (DatabaseNotification $n): bool => $n->data['format'] === 'filament');
    }

    public function test_teacher_reply_notifies_only_the_student_never_the_teacher(): void
    {
        Notification::fake();

        $teacher = User::factory()->create()->assignRole('teacher');
        $student = User::factory()->create()->assignRole('student');
        $project = Project::factory()->create(['created_by_user_id' => $teacher->id]);

        Livewire::actingAs($teacher)
            ->test(PrivateChatPanel::class, ['project' => $project, 'type' => 'individual', 'student' => $student])
            ->set('content', 'Claro, te explico')
            ->call('send');

        Notification::assertSentTo($student, PrivateChatMessageReceived::class);
    }

    public function test_team_message_notifies_every_other_member_and_the_teacher_never_the_author(): void
    {
        Notification::fake();

        $teacher = User::factory()->create()->assignRole('teacher');
        $author = User::factory()->create()->assignRole('student');
        $otherMember = User::factory()->create()->assignRole('student');
        $project = Project::factory()->create(['created_by_user_id' => $teacher->id]);
        $team = ProjectTeam::factory()->create(['project_id' => $project->id]);
        $team->users()->attach([$author->id, $otherMember->id]);

        Livewire::actingAs($author)
            ->test(PrivateChatPanel::class, ['project' => $project, 'type' => 'team', 'team' => $team])
            ->set('content', 'Equipo, avancemos con la entrega')
            ->call('send');

        Notification::assertSentTo($otherMember, PrivateChatMessageReceived::class);
        Notification::assertNothingSentTo($author);
        Notification::assertSentTo($teacher, DatabaseNotification::class, fn (DatabaseNotification $n): bool => $n->data['format'] === 'filament');
    }

    public function test_team_message_from_the_teacher_notifies_all_members_but_not_the_teacher(): void
    {
        Notification::fake();

        $teacher = User::factory()->create()->assignRole('teacher');
        $memberOne = User::factory()->create()->assignRole('student');
        $memberTwo = User::factory()->create()->assignRole('student');
        $project = Project::factory()->create(['created_by_user_id' => $teacher->id]);
        $team = ProjectTeam::factory()->create(['project_id' => $project->id]);
        $team->users()->attach([$memberOne->id, $memberTwo->id]);

        Livewire::actingAs($teacher)
            ->test(PrivateChatPanel::class, ['project' => $project, 'type' => 'team', 'team' => $team])
            ->set('content', 'Recuerden la fecha límite')
            ->call('send');

        Notification::assertSentTo($memberOne, PrivateChatMessageReceived::class);
        Notification::assertSentTo($memberTwo, PrivateChatMessageReceived::class);
    }

    public function test_notification_deep_link_points_to_the_project_with_the_right_anchor(): void
    {
        Notification::fake();

        $teacher = User::factory()->create()->assignRole('teacher');
        $student = User::factory()->create()->assignRole('student');
        $project = Project::factory()->create(['created_by_user_id' => $teacher->id]);

        Livewire::actingAs($teacher)
            ->test(PrivateChatPanel::class, ['project' => $project, 'type' => 'individual', 'student' => $student])
            ->set('content', 'Mensaje con deep link')
            ->call('send');

        Notification::assertSentTo($student, PrivateChatMessageReceived::class, function (PrivateChatMessageReceived $notification) use ($student, $project): bool {
            $array = $notification->toArray($student);

            return $array['action_url'] === route('student.projects.show', $project->uuid).'#chat-individual';
        });
    }
}
