<?php

declare(strict_types=1);

namespace App\Livewire\Parent;

use App\Models\User;
use App\Modules\Tracking\Actions\AggregateThinkingFieldProgressAction;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Primer nivel del drill-down del acudiente (campo de pensamiento →
 * proyectos → detalle de proyecto/evidencia). Estrictamente de solo
 * lectura: mount()/render() son los únicos métodos públicos -- no hay
 * ningún wire:click posible que mute nada de $child.
 *
 * hasRole('parent') + isGuardianOf($child) explícitos en mount() (defensa
 * en profundidad, mismo patrón que ForumThreadShow/GroupChat con
 * hasRole('student')): el middleware role:parent de la ruta y la Policy no
 * son el único punto de verdad -- que {child:uuid} resuelva a un User real
 * no basta, debe ser hijo del acudiente autenticado.
 *
 * Reutiliza AggregateThinkingFieldProgressAction tal cual (Hito 4b): ya
 * recibe el estudiante objetivo como parámetro explícito, nunca lee
 * auth()->id() -- mismo patrón que debe seguir el resto de este
 * drill-down.
 */
#[Layout('layouts.portal')]
class ChildThinkingFields extends Component
{
    public User $child;

    public function mount(User $child): void
    {
        abort_unless(auth()->user()->hasRole('parent') && auth()->user()->isGuardianOf($child), 403);

        $this->child = $child;
    }

    public function render()
    {
        return view('livewire.parent.child-thinking-fields', [
            'fields' => app(AggregateThinkingFieldProgressAction::class)->execute($this->child),
        ]);
    }
}
