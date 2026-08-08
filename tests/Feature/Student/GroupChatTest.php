<?php

namespace Tests\Feature\Student;

use App\Livewire\Student\GroupChat;
use App\Models\User;
use App\Modules\Community\Models\ChatMessage;
use App\Modules\Institution\Models\Group;
use Database\Seeders\RoleLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Sin group_id en la URL: siempre es el grupo propio del estudiante
 * autenticado, así que el aislamiento entre grupos se prueba armando dos
 * estudiantes de grupos distintos y confirmando que cada uno solo ve los
 * mensajes de su propio grupo.
 */
class GroupChatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLevelSeeder::class);
    }

    public function test_student_sees_only_messages_from_own_group(): void
    {
        $ownGroup = Group::factory()->create();
        $otherGroup = Group::factory()->create();
        $student = User::factory()->create(['group_id' => $ownGroup->id])->assignRole('student');

        ChatMessage::factory()->create(['group_id' => $ownGroup->id, 'content' => 'Mensaje de mi grupo']);
        ChatMessage::factory()->create(['group_id' => $otherGroup->id, 'content' => 'Mensaje de otro grupo']);

        $response = $this->actingAs($student)->get(route('student.chat'));

        $response->assertOk();
        $response->assertSee('Mensaje de mi grupo');
        $response->assertDontSee('Mensaje de otro grupo');
    }

    public function test_student_can_send_a_message_to_own_group(): void
    {
        $group = Group::factory()->create();
        $student = User::factory()->create(['group_id' => $group->id])->assignRole('student');

        $this->actingAs($student);

        Livewire::test(GroupChat::class)
            ->set('content', 'Hola grupo')
            ->call('send');

        $this->assertDatabaseHas('chat_messages', [
            'group_id' => $group->id,
            'user_id' => $student->id,
            'content' => 'Hola grupo',
        ]);
    }

    public function test_student_without_a_group_sees_an_empty_state(): void
    {
        $student = User::factory()->create(['group_id' => null])->assignRole('student');

        $response = $this->actingAs($student)->get(route('student.chat'));

        $response->assertOk();
        $response->assertSee('Todavía no tienes un grupo asignado');
    }

    public function test_student_without_a_group_cannot_send_a_message(): void
    {
        $student = User::factory()->create(['group_id' => null])->assignRole('student');

        $this->actingAs($student);

        Livewire::test(GroupChat::class)
            ->set('content', 'Intento sin grupo')
            ->call('send')
            ->assertForbidden();

        $this->assertDatabaseMissing('chat_messages', ['content' => 'Intento sin grupo']);
    }

    /**
     * Defensa en profundidad: el middleware role:student bloquea ANTES de
     * llegar a ChatMessagePolicy -- notable aquí en particular porque
     * ChatMessagePolicy sí le permitiría a cualquier staff ver/enviar en
     * cualquier grupo (aproximación pragmática, ver TODO.md #15); lo que
     * prueba este test es que un teacher jamás llega a esa Policy por esta
     * ruta del portal de estudiante, sin importar lo que la Policy diría.
     */
    public function test_teacher_cannot_access_chat_route_middleware(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');

        $this->actingAs($teacher)
            ->get(route('student.chat'))
            ->assertForbidden();
    }

    public function test_parent_cannot_access_chat_route_middleware(): void
    {
        $parent = User::factory()->create()->assignRole('parent');

        $this->actingAs($parent)
            ->get(route('student.chat'))
            ->assertForbidden();
    }
}
