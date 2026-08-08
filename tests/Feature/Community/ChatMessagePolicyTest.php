<?php

namespace Tests\Feature\Community;

use App\Models\User;
use App\Modules\Community\Models\ChatMessage;
use App\Modules\Institution\Models\Group;
use Database\Seeders\RoleLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * chat_messages se aísla por group_id, no por ciclo/proyecto -- dos grupos
 * del mismo ciclo siguen siendo chats distintos e inaccesibles entre sí para
 * un estudiante.
 */
class ChatMessagePolicyTest extends TestCase
{
    use RefreshDatabase;

    private Group $ownGroup;

    private Group $otherGroup;

    private User $studentOwnGroup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLevelSeeder::class);

        $this->ownGroup = Group::factory()->create();
        $this->otherGroup = Group::factory()->create();

        $this->studentOwnGroup = User::factory()->create(['group_id' => $this->ownGroup->id])
            ->assignRole('student');
    }

    public function test_student_can_view_message_in_own_group(): void
    {
        $message = ChatMessage::factory()->create(['group_id' => $this->ownGroup->id]);

        $this->assertTrue($this->studentOwnGroup->can('view', $message));
    }

    public function test_student_cannot_view_message_in_other_group(): void
    {
        $message = ChatMessage::factory()->create(['group_id' => $this->otherGroup->id]);

        $this->assertFalse($this->studentOwnGroup->can('view', $message));
    }

    public function test_student_cannot_view_a_hidden_message_even_in_own_group(): void
    {
        $message = ChatMessage::factory()->create([
            'group_id' => $this->ownGroup->id,
            'is_hidden' => true,
        ]);

        $this->assertFalse($this->studentOwnGroup->can('view', $message));
    }

    public function test_parent_cannot_view_any_chat_message_fail_closed(): void
    {
        $parent = User::factory()->create()->assignRole('parent');
        $message = ChatMessage::factory()->create(['group_id' => $this->ownGroup->id]);

        $this->assertFalse($parent->can('view', $message));
    }

    public function test_teacher_can_view_message_in_any_group_pragmatic_approximation(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');
        $message = ChatMessage::factory()->create(['group_id' => $this->otherGroup->id]);

        $this->assertTrue($teacher->can('view', $message));
    }

    public function test_student_can_send_message_in_own_group(): void
    {
        $this->assertTrue($this->studentOwnGroup->can('create', [ChatMessage::class, $this->ownGroup]));
    }

    public function test_student_cannot_send_message_in_other_group(): void
    {
        $this->assertFalse($this->studentOwnGroup->can('create', [ChatMessage::class, $this->otherGroup]));
    }

    public function test_student_can_never_hide_a_message(): void
    {
        $message = ChatMessage::factory()->create(['group_id' => $this->ownGroup->id]);

        $this->assertFalse($this->studentOwnGroup->can('hide', $message));
    }

    public function test_teacher_cannot_hide_a_message(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');
        $message = ChatMessage::factory()->create(['group_id' => $this->ownGroup->id]);

        $this->assertFalse($teacher->can('hide', $message));
    }

    public function test_coordinator_can_hide_a_message(): void
    {
        $coordinator = User::factory()->create()->assignRole('coordinator');
        $message = ChatMessage::factory()->create(['group_id' => $this->ownGroup->id]);

        $this->assertTrue($coordinator->can('hide', $message));
    }

    public function test_rector_can_hide_a_message(): void
    {
        $rector = User::factory()->create()->assignRole('rector');
        $message = ChatMessage::factory()->create(['group_id' => $this->ownGroup->id]);

        $this->assertTrue($rector->can('hide', $message));
    }
}
