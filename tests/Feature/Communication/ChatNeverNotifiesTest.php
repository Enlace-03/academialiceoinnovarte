<?php

namespace Tests\Feature\Communication;

use App\Models\User;
use App\Modules\Community\Actions\SendChatMessageAction;
use App\Modules\Institution\Models\Cycle;
use App\Modules\Institution\Models\Group;
use App\Modules\Institution\Models\Institution;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Chat nunca genera notificación de ningún tipo (decisión confirmada del
 * Hito 5a): evita saturar con volumen. No hay ningún listener de
 * Communication colgado de ChatMessageSent -- solo el de Tracking
 * (RecordChatMessageSent), que no notifica a nadie.
 */
class ChatNeverNotifiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_sending_a_chat_message_never_triggers_any_notification(): void
    {
        $this->seed(RolePermissionSeeder::class);
        Institution::factory()->create();
        $cycle = Cycle::factory()->create();
        $group = Group::factory()->create(['cycle_id' => $cycle->id]);
        $student = User::factory()->create(['group_id' => $group->id])->assignRole('student');

        Notification::fake();

        app(SendChatMessageAction::class)->execute($group, $student, 'hola grupo');

        Notification::assertNothingSent();
    }
}
