<?php

declare(strict_types=1);

namespace App\Modules\Project\Observers;

use App\Modules\Project\Models\Phase;
use App\Modules\Project\Models\Project;

/**
 * Garantiza que TODO Project, sin importar cómo se cree (Filament, tinker,
 * factory, futura API), nazca con sus 4 fases institucionales fijas y en
 * orden — nunca queda a criterio de quien lo crea armarlas a mano.
 */
class ProjectObserver
{
    public function created(Project $project): void
    {
        foreach (Phase::INSTITUTIONAL_NAMES as $order => $name) {
            $project->phases()->create([
                'name' => $name,
                'order' => $order,
            ]);
        }
    }
}
