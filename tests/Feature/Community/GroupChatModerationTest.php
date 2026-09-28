<?php

namespace Tests\Feature\Community;

use App\Filament\Academic\Pages\GroupChatModeration;
use App\Models\User;
use App\Modules\Community\Models\ChatMessage;
use App\Modules\Institution\Models\Group;
use Database\Seeders\RoleLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GroupChatModerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLevelSeeder::class);

        Filament::setCurrentPanel(Filament::getPanel('academic'));
    }

    public function test_rector_can_access_the_page(): void
    {
        $rector = User::factory()->create()->assignRole('rector');
        $this->actingAs($rector);

        $this->assertTrue(GroupChatModeration::canAccess());
    }

    public function test_teacher_cannot_access_the_page(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');
        $this->actingAs($teacher);

        $this->assertFalse(GroupChatModeration::canAccess());
    }

    public function test_rector_can_hide_a_message(): void
    {
        $group = Group::factory()->create();
        $student = User::factory()->create(['group_id' => $group->id])->assignRole('student');
        $message = ChatMessage::factory()->create(['group_id' => $group->id, 'user_id' => $student->id]);

        $rector = User::factory()->create()->assignRole('rector');
        $this->actingAs($rector);

        Livewire::test(GroupChatModeration::class)
            ->set('groupId', $group->id)
            ->call('hide', $message->id);

        $message->refresh();
        $this->assertTrue($message->is_hidden);
        $this->assertSame($rector->id, $message->hidden_by_user_id);
    }

    /**
     * hide() llama a $this->authorize('hide', $message) por sí mismo (no
     * depende solo de canAccess(), que gatea la navegación/el montaje de la
     * página, no cada acción individual -- mismo criterio de defensa en
     * profundidad que GroupChat::mount() con hasRole('student')). Se prueba
     * la Policy directamente: mount() de la página vía Livewire::test() como
     * un teacher sin chat.moderate ya queda bloqueado por Filament antes de
     * llegar a hide() (ver test_teacher_cannot_access_the_page), así que no
     * hay forma realista de ejercitar esta rama a través de la página misma.
     */
    public function test_teacher_cannot_hide_a_message_per_the_policy(): void
    {
        $group = Group::factory()->create();
        $student = User::factory()->create(['group_id' => $group->id])->assignRole('student');
        $message = ChatMessage::factory()->create(['group_id' => $group->id, 'user_id' => $student->id]);

        $teacher = User::factory()->create()->assignRole('teacher');

        $this->assertFalse($teacher->can('hide', $message));
    }

    public function test_a_student_stops_seeing_a_hidden_message(): void
    {
        $group = Group::factory()->create();
        $student = User::factory()->create(['group_id' => $group->id])->assignRole('student');
        $message = ChatMessage::factory()->create([
            'group_id' => $group->id,
            'user_id' => $student->id,
            'content' => 'Mensaje que será ocultado',
        ]);

        $rector = User::factory()->create()->assignRole('rector');
        $this->actingAs($rector);

        Livewire::test(GroupChatModeration::class)
            ->set('groupId', $group->id)
            ->call('hide', $message->id);

        $response = $this->actingAs($student)->get(route('student.chat'));

        $response->assertOk();
        $response->assertDontSee('Mensaje que será ocultado');
    }
}
