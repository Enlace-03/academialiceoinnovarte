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

    public function markAsRead(string $notificationId): void
    {
        auth()->user()->notifications()->where('id', $notificationId)->first()?->markAsRead();
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
