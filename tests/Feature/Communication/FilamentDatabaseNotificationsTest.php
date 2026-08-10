<?php

namespace Tests\Feature\Communication;

use App\Filament\Academic\Livewire\DatabaseNotifications;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "Limpiar" en /academia (segunda vuelta de la tercera vuelta del Hito 5):
 * mismo criterio que NotificationBell del portal -- nunca DELETE real,
 * siempre archived_at. Ambos consumidores de la misma tabla física
 * `notifications` tratan el dato igual, aquí se confirma del lado de
 * Filament con el mismo tipo de aserción ya usada para "Archivar" en
 * NotificationBellTest.
 */
class FilamentDatabaseNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        Filament::setCurrentPanel(Filament::getPanel('academic'));

        $this->teacher = User::factory()->create()->assignRole('teacher');
        $this->actingAs($this->teacher);
    }

    private function makeFilamentNotification(): DatabaseNotification
    {
        $notification = new DatabaseNotification();
        $notification->id = (string) Str::uuid();
        $notification->type = 'Filament\\Notifications\\DatabaseNotification';
        $notification->notifiable_type = User::class;
        $notification->notifiable_id = $this->teacher->id;
        $notification->data = ['format' => 'filament', 'title' => 'Nueva entrega registrada', 'body' => 'Prueba'];
        $notification->read_at = null;
        $notification->archived_at = null;
        $notification->save();

        return $notification;
    }

    public function test_clearing_notifications_leaves_the_rows_intact_in_the_database(): void
    {
        $first = $this->makeFilamentNotification();
        $second = $this->makeFilamentNotification();

        Livewire::test(DatabaseNotifications::class)
            ->call('clearNotifications');

        $this->assertSame(2, DatabaseNotification::where('notifiable_id', $this->teacher->id)->count());
        $this->assertNotNull(DatabaseNotification::find($first->id)->archived_at);
        $this->assertNotNull(DatabaseNotification::find($second->id)->archived_at);
    }

    public function test_cleared_notifications_no_longer_appear_in_the_panel(): void
    {
        $notification = $this->makeFilamentNotification();

        $component = Livewire::test(DatabaseNotifications::class);
        $this->assertSame(1, $component->instance()->getNotifications()->count());

        $component->call('clearNotifications');
        $this->assertSame(0, $component->instance()->getNotifications()->count());

        $this->assertNotNull(DatabaseNotification::find($notification->id));
    }
}
