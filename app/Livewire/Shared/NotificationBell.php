<?php

declare(strict_types=1);

namespace App\Livewire\Shared;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Campanita de notificaciones del portal de estudiante/acudiente (Hito 5a,
 * ampliada en la tercera vuelta): filtro no leídas/todas, carga incremental
 * (sin límite fijo de 10 como antes -- confirmado que ni Filament ni esta
 * campanita tenían paginación real), y archivar (nunca borrar -- mismo
 * criterio de trazabilidad que StudentSessionGrant.ended_at /
 * DataTreatmentConsent). Filament (/academia) ignora por completo la
 * columna archived_at; su query no la conoce, así que agregarla es segura
 * para ambos consumidores de la misma tabla física `notifications`.
 */
class NotificationBell extends Component
{
    private const PAGE_SIZE = 10;

    public bool $open = false;

    public string $filter = 'all';

    public int $perPage = self::PAGE_SIZE;

    public function toggle(): void
    {
        $this->open = ! $this->open;
    }

    public function setFilter(string $filter): void
    {
        $this->filter = in_array($filter, ['all', 'unread'], true) ? $filter : 'all';
        $this->perPage = self::PAGE_SIZE;
    }

    public function loadMore(): void
    {
        $this->perPage += self::PAGE_SIZE;
    }

    /**
     * Marca como leída Y navega a su destino -- mismo patrón de cualquier
     * centro de notificaciones (Gmail, Slack): un clic hace las dos cosas.
     *
     * NO se puede llamar open() -- colisiona con la propiedad pública $open
     * del dropdown ($wire.open resuelve a la propiedad, no al método, y
     * Alpine falla con "$wire.open is not a function"; bug real encontrado
     * en la verificación manual del Hito 5, segunda vuelta).
     */
    public function visit(string $notificationId): void
    {
        $notification = $this->baseQuery()->where('id', $notificationId)->first();

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

    /**
     * Oculta de la vista normal sin borrar el registro. No hay vista de
     * "archivadas" para el usuario final a propósito -- si algún día hace
     * falta consultarlas, es un reporte de personal en /academia, no una
     * funcionalidad nueva del portal.
     */
    public function archive(string $notificationId): void
    {
        $this->baseQuery()->where('id', $notificationId)->update(['archived_at' => now()]);
    }

    public function markAllAsRead(): void
    {
        $this->baseQuery()->whereNull('read_at')->update(['read_at' => now()]);
    }

    private function baseQuery(): Builder
    {
        return DatabaseNotification::query()
            ->where('notifiable_type', get_class(auth()->user()))
            ->where('notifiable_id', auth()->id())
            ->whereNull('archived_at');
    }

    private function filteredQuery(): Builder
    {
        $query = $this->baseQuery();

        if ($this->filter === 'unread') {
            $query->whereNull('read_at');
        }

        return $query;
    }

    public function recentNotifications(): Collection
    {
        return $this->filteredQuery()->latest()->limit($this->perPage)->get();
    }

    public function totalFilteredCount(): int
    {
        return $this->filteredQuery()->count();
    }

    /**
     * Excluye archivadas a propósito: archivar una notificación no leída
     * la saca de la cuenta -- es justamente lo que "ocultar de la vista
     * normal" significa, no tendría sentido que el badge siguiera
     * contándola si el usuario ya la archivó.
     */
    public function unreadCount(): int
    {
        return $this->baseQuery()->whereNull('read_at')->count();
    }

    public function render()
    {
        $notifications = $this->recentNotifications();

        return view('livewire.shared.notification-bell', [
            'notifications' => $notifications,
            'unreadCount' => $this->unreadCount(),
            'hasMore' => $this->totalFilteredCount() > $notifications->count(),
        ]);
    }
}
