<?php

declare(strict_types=1);

namespace App\Livewire\Shared;

use App\Models\User;
use App\Modules\Community\Models\GalleryPost;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Galería (Hito de galería): lista cronológica para estudiante y acudiente,
 * sin comentarios ni likes -- solo visualización. La audiencia se resuelve
 * con el mismo criterio que GalleryPostPolicy::view() (general siempre
 * visible; de proyecto según el/los ciclo(s) accesibles), pero calculada
 * como query directa en vez de Policy por fila -- evita N+1 chequeos de
 * autorización sobre un listado potencialmente largo. GalleryPostPolicy
 * sigue siendo la autoridad real para el acceso a cada foto individual (ver
 * routes/web.php, galeria.fotos.show), así que ambos caminos deben dar el
 * mismo resultado -- se prueba explícitamente en GalleryVisibilityTest.
 */
#[Layout('layouts.portal')]
class Gallery extends Component
{
    public function mount(): void
    {
        $user = auth()->user();

        abort_unless($user->hasRole('student') || $user->hasRole('parent'), 403);
    }

    public function posts(): Collection
    {
        $cycleIds = $this->accessibleCycleIds(auth()->user());

        return GalleryPost::query()
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where(function ($query) use ($cycleIds) {
                $query->whereNull('project_id');

                if ($cycleIds !== []) {
                    $query->orWhereHas('project', fn ($q) => $q->whereIn('cycle_id', $cycleIds));
                }
            })
            ->with('photos')
            ->latest('published_at')
            ->get();
    }

    /**
     * @return array<int>
     */
    private function accessibleCycleIds(User $user): array
    {
        if ($user->hasRole('student')) {
            return $user->schoolGrade !== null ? [$user->schoolGrade->cycle_id] : [];
        }

        if ($user->hasRole('parent')) {
            return $user->children()->with('schoolGrade')->get()
                ->pluck('schoolGrade.cycle_id')
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        return [];
    }

    public function render()
    {
        return view('livewire.shared.gallery', [
            'posts' => $this->posts(),
        ]);
    }
}
