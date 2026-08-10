<?php

declare(strict_types=1);

namespace App\Filament\Academic\Livewire;

use Filament\Livewire\DatabaseNotifications as BaseDatabaseNotifications;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Sobrescribe clearNotifications() para archivar (columna archived_at) en
 * vez de borrar -- mismo criterio y misma columna exacta que
 * NotificationBell del portal (Hito 5, tercera vuelta). Ambos consumidores
 * de la misma tabla física `notifications` tratan el dato de la misma
 * forma: nunca se pierde el registro, solo se oculta de la vista normal.
 * Registrada en AcademicPanelProvider vía
 * ->databaseNotifications(livewireComponent: self::class).
 */
class DatabaseNotifications extends BaseDatabaseNotifications
{
    public function clearNotifications(): void
    {
        $this->getNotificationsQuery()->update(['archived_at' => now()]);
    }

    public function getNotificationsQuery(): Builder | Relation
    {
        return parent::getNotificationsQuery()->whereNull('archived_at');
    }
}
