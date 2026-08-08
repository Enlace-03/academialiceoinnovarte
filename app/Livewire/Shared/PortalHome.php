<?php

declare(strict_types=1);

namespace App\Livewire\Shared;

use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Placeholder post-login del panel fuera de Filament (Hito 3b-0). Las
 * pantallas reales del portal de estudiante (Mis Proyectos, Vista de Hilo,
 * Chat) se construyen en el Hito 3b, con su propia especificación.
 */
#[Layout('layouts.portal')]
class PortalHome extends Component
{
    public function render()
    {
        return view('livewire.shared.portal-home');
    }
}
