<?php

declare(strict_types=1);

namespace App\Livewire\Student;

use App\Modules\Community\Actions\CreateForumThreadAction;
use App\Modules\Community\Models\ForumThread;
use App\Modules\Project\Models\Project;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Embebido en ProjectShow, no tiene ruta propia. Hilos del proyecto (y de
 * cada fase, mostrando la fase como etiqueta). La visibilidad real la
 * garantiza ForumThreadPolicy en createThread(); la consulta de listado
 * replica el mismo filtro (is_hidden=false) porque un estudiante nunca debe
 * ver un hilo oculto, ni siquiera en la lista.
 */
class ForumThreadList extends Component
{
    public Project $project;

    #[Validate('required|string|max:150')]
    public string $newThreadTitle = '';

    public ?int $newThreadPhaseId = null;

    public bool $showForm = false;

    public function threads(): Collection
    {
        return $this->project->forumThreads()
            ->where('is_hidden', false)
            ->with('phase')
            ->withCount('posts')
            ->latest()
            ->get();
    }

    public function createThread(): void
    {
        $this->authorize('create', [ForumThread::class, $this->project]);

        $this->validate();

        $thread = app(CreateForumThreadAction::class)->execute($this->project, auth()->user(), [
            'title' => $this->newThreadTitle,
            'phase_id' => $this->newThreadPhaseId,
        ]);

        $this->redirectRoute('student.forum.show', ['project' => $this->project->uuid, 'thread' => $thread->uuid], navigate: true);
    }

    public function render()
    {
        return view('livewire.student.forum-thread-list', [
            'threads' => $this->threads(),
            'phases' => $this->project->phases,
        ]);
    }
}
