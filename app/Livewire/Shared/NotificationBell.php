<?php

declare(strict_types=1);

namespace App\Livewire\Shared;

use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Campanita de notificaciones del portal de estudiante/acudiente (Hito 5a).
 * Lee directo de la tabla notifications de auth()->user() (Notifiable ya
 * está en User) -- sin Action, es una lectura simple igual que
 * MyProjects::projects().
 */
class NotificationBell extends Component
{
    public bool $open = false;

    public function toggle(): void
    {
        $this->open = ! $this->open;
    }

    /**
     * Marca como leída Y navega a su destino -- mismo patrón de cualquier
     * centro de notificaciones (Gmail, Slack): un clic hace las dos cosas.
     * markAllAsRead() se queda solo marcando, sin navegar (acción masiva).
     *
     * NO se puede llamar open() -- colisiona con la propiedad pública $open
     * del dropdown ($wire.open resuelve a la propiedad, no al método, y
     * Alpine falla con "$wire.open is not a function"; bug real encontrado
     * en la verificación manual de este mismo cambio).
     */
    public function visit(string $notificationId): void
    {
        $notification = auth()->user()->notifications()->where('id', $notificationId)->first();

        if ($notification === null) {
            return;
        }

        $notification->markAsRead();

        $url = $notification->data['action_url'] ?? null;

        if ($url !== null) {
            // navigate:true (SPA, sin recarga) NO dispara el scroll nativo
            // del navegador hacia un #fragmento -- confirmado en vivo
            // (scrollY se quedaba en 0 con #fase-{id} en la URL). Recarga
            // completa aquí, la única forma real de que el ancla funcione.
            $this->redirect($url);
        }
    }

    public function markAllAsRead(): void
    {
        auth()->user()->unreadNotifications->markAsRead();
    }

    public function recentNotifications(): Collection
    {
        return DatabaseNotification::query()
            ->where('notifiable_type', get_class(auth()->user()))
            ->where('notifiable_id', auth()->id())
            ->latest()
            ->limit(10)
            ->get();
    }

    public function unreadCount(): int
    {
        return auth()->user()->unreadNotifications()->count();
    }

    public function render()
    {
        return view('livewire.shared.notification-bell', [
            'notifications' => $this->recentNotifications(),
            'unreadCount' => $this->unreadCount(),
        ]);
    }
}
