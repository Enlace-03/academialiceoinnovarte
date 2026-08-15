<?php

declare(strict_types=1);

namespace App\Filament\Academic\Pages;

use App\Modules\Community\Models\PrivateChatThread;
use App\Modules\Project\Models\Project;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Visibilidad institucional del chat privado (coordinator/rector,
 * private_chats.view.all): lista TODOS los proyectos, no solo los propios
 * -- a diferencia de ProjectResource::getEloquentQuery() (own/all), acá la
 * autoridad es la visibilidad institucional del chat, independiente de
 * quién sea dueño del proyecto.
 *
 * Reutiliza App\Livewire\Shared\PrivateChatPanel para la conversación
 * seleccionada -- mismo componente que estudiante y docente, así que
 * "puede enviar"/"puede ocultar" los sigue decidiendo
 * PrivateChatThreadPolicy, nunca esta página (que solo filtra qué
 * proyecto/hilo mostrar).
 */
class PrivateChats extends Page
{
    protected static string | UnitEnum | null $navigationGroup = 'Comunidad';

    protected static ?string $navigationLabel = 'Chats privados';

    protected static ?string $title = 'Chats privados — visibilidad institucional';

    protected static ?string $slug = 'chats-privados';

    protected string $view = 'filament.academic.pages.private-chats';

    public ?int $projectId = null;

    public ?int $threadId = null;

    public static function canAccess(): bool
    {
        return Auth::user()?->hasPermissionTo('private_chats.view.all') ?? false;
    }

    /**
     * @return Collection<int, Project>
     */
    public function projects(): Collection
    {
        return Project::query()->orderBy('title')->get();
    }

    /**
     * @return Collection<int, PrivateChatThread>
     */
    public function threads(): Collection
    {
        if ($this->projectId === null) {
            return collect();
        }

        return PrivateChatThread::query()
            ->where('project_id', $this->projectId)
            ->withCount('messages')
            ->with(['student', 'team'])
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Hook de ciclo de vida de Livewire (updated{Propiedad}) -- se dispara
     * solo al cambiar de proyecto vía wire:model.live en el <select> del
     * Blade, nunca al recargar la página con el mismo valor.
     */
    public function updatedProjectId(): void
    {
        $this->threadId = null;
    }

    public function selectThread(int $threadId): void
    {
        $this->threadId = $threadId;
    }

    public function selectedThread(): ?PrivateChatThread
    {
        if ($this->threadId === null) {
            return null;
        }

        return PrivateChatThread::with(['project', 'student', 'team'])->find($this->threadId);
    }
}
