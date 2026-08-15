<?php

namespace Tests\Feature\Shared;

use App\Livewire\Shared\NotificationBell;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Filtro no leídas/todas, carga incremental y archivar (tercera vuelta del
 * Hito 5). No usa notificaciones reales de dominio -- crea filas directo en
 * `notifications` porque estos tests son sobre el comportamiento del propio
 * componente de la campanita, no sobre qué dispara cada tipo (eso ya está
 * cubierto en NotificationActionUrlTest / NotificationIsolationTest).
 */
class NotificationBellTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->student = User::factory()->create()->assignRole('student');
    }

    private function makeNotification(bool $read = false, bool $archived = false): DatabaseNotification
    {
        $notification = new DatabaseNotification();
        $notification->id = (string) Str::uuid();
        $notification->type = 'App\\Modules\\Communication\\Notifications\\ForumReplyReceived';
        $notification->notifiable_type = User::class;
        $notification->notifiable_id = $this->student->id;
        $notification->data = ['type' => 'forum_reply_received', 'author_name' => 'Alguien'];
        $notification->read_at = $read ? now() : null;
        $notification->archived_at = $archived ? now() : null;
        $notification->save();

        return $notification;
    }

    public function test_unread_filter_shows_only_unread_notifications(): void
    {
        $this->makeNotification(read: false);
        $this->makeNotification(read: true);

        Livewire::actingAs($this->student)->test(NotificationBell::class)
            ->call('setFilter', 'unread')
            ->assertViewHas('notifications', fn ($notifications) => $notifications->count() === 1);
    }

    public function test_all_filter_shows_read_and_unread_notifications(): void
    {
        $this->makeNotification(read: false);
        $this->makeNotification(read: true);

        Livewire::actingAs($this->student)->test(NotificationBell::class)
            ->call('setFilter', 'all')
            ->assertViewHas('notifications', fn ($notifications) => $notifications->count() === 2);
    }

    public function test_mark_all_as_read_does_not_delete_anything(): void
    {
        $this->makeNotification(read: false);
        $this->makeNotification(read: false);

        Livewire::actingAs($this->student)->test(NotificationBell::class)
            ->call('markAllAsRead');

        $this->assertSame(2, DatabaseNotification::where('notifiable_id', $this->student->id)->count());
        $this->assertSame(0, DatabaseNotification::where('notifiable_id', $this->student->id)->whereNull('read_at')->count());
    }

    public function test_archiving_hides_the_notification_without_deleting_the_database_row(): void
    {
        $notification = $this->makeNotification(read: false);

        Livewire::actingAs($this->student)->test(NotificationBell::class)
            ->call('archive', $notification->id)
            ->assertViewHas('notifications', fn ($notifications) => $notifications->isEmpty());

        $this->assertNotNull(DatabaseNotification::find($notification->id));
        $this->assertNotNull(DatabaseNotification::find($notification->id)->archived_at);
    }

    public function test_archiving_an_unread_notification_removes_it_from_the_unread_count(): void
    {
        $notification = $this->makeNotification(read: false);

        $component = Livewire::actingAs($this->student)->test(NotificationBell::class);
        $component->assertViewHas('unreadCount', 1);

        $component->call('archive', $notification->id)
            ->assertViewHas('unreadCount', 0);
    }

    /**
     * Bug real encontrado en la verificación manual del chat privado: el
     * @switch de tipos en la vista no tenía un @case para
     * 'private_chat_message_received' y caía al @default genérico
     * ("Tienes una notificación nueva"), aunque la Notification sí traía
     * todos los datos necesarios (author_name/thread_type/project_title).
     */
    public function test_private_chat_message_notification_renders_its_specific_text(): void
    {
        $notification = new DatabaseNotification();
        $notification->id = (string) Str::uuid();
        $notification->type = 'App\\Modules\\Communication\\Notifications\\PrivateChatMessageReceived';
        $notification->notifiable_type = User::class;
        $notification->notifiable_id = $this->student->id;
        $notification->data = [
            'type' => 'private_chat_message_received',
            'author_name' => 'Docente Prueba',
            'thread_type' => 'individual',
            'project_title' => 'El agua que compartimos',
        ];
        $notification->save();

        Livewire::actingAs($this->student)->test(NotificationBell::class)
            ->call('toggle')
            ->assertSee('Docente Prueba te escribió en el chat privado de "El agua que compartimos".', false)
            ->assertDontSee('Tienes una notificación nueva.');
    }

    public function test_pagination_does_not_break_the_unread_count_of_the_bell(): void
    {
        for ($i = 0; $i < 15; $i++) {
            $this->makeNotification(read: false);
        }

        $component = Livewire::actingAs($this->student)->test(NotificationBell::class);

        // Página inicial: solo 10 visibles, pero el badge cuenta las 15 reales.
        $component->assertViewHas('notifications', fn ($notifications) => $notifications->count() === 10)
            ->assertViewHas('unreadCount', 15)
            ->assertViewHas('hasMore', true);

        $component->call('loadMore')
            ->assertViewHas('notifications', fn ($notifications) => $notifications->count() === 15)
            ->assertViewHas('unreadCount', 15)
            ->assertViewHas('hasMore', false);
    }
}
