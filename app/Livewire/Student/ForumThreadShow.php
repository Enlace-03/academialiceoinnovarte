<?php

declare(strict_types=1);

namespace App\Livewire\Student;

use App\Modules\Community\Actions\CreateForumPostAction;
use App\Modules\Community\Actions\ToggleForumPostLikeAction;
use App\Modules\Community\Models\ForumPost;
use App\Modules\Community\Models\ForumThread;
use App\Modules\Project\Models\Project;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Respuestas de un solo nivel: replyingToPostId solo puede apuntar a un post
 * raíz (parent_post_id null) — el botón "responder" ni siquiera se muestra
 * para una respuesta (ver vista), y CreateForumPostAction rechaza el intento
 * de todas formas si se fuerza por wire:click manipulado.
 *
 * El conteo de likes es público para quien ve el post; la lista de quién dio
 * like NUNCA se carga aquí (ForumPostPolicy::viewLikers es solo personal).
 */
#[Layout('layouts.portal')]
class ForumThreadShow extends Component
{
    public Project $project;

    public ForumThread $thread;

    #[Validate('required|string|max:2000')]
    public string $newPostContent = '';

    public ?int $replyingToPostId = null;

    #[Validate('required|string|max:2000')]
    public string $replyContent = '';

    public function mount(Project $project, ForumThread $thread): void
    {
        $this->authorize('view', $thread);

        if ($thread->project_id !== $project->id) {
            throw new NotFoundHttpException();
        }

        $this->project = $project;
        $this->thread = $thread;
    }

    public function posts(): Collection
    {
        return $this->thread->posts()
            ->whereNull('parent_post_id')
            ->where('is_hidden', false)
            ->with(['user', 'replies' => fn ($query) => $query->where('is_hidden', false)->with('user')])
            ->withCount('likes')
            ->oldest()
            ->get();
    }

    public function createPost(): void
    {
        $this->authorize('create', [ForumPost::class, $this->thread]);

        $this->validateOnly('newPostContent');

        app(CreateForumPostAction::class)->execute($this->thread, auth()->user(), [
            'content' => $this->newPostContent,
        ]);

        $this->newPostContent = '';
    }

    public function startReply(int $postId): void
    {
        $this->replyingToPostId = $postId;
        $this->replyContent = '';
    }

    public function cancelReply(): void
    {
        $this->replyingToPostId = null;
        $this->replyContent = '';
    }

    public function submitReply(): void
    {
        $this->authorize('create', [ForumPost::class, $this->thread]);

        $this->validateOnly('replyContent');

        app(CreateForumPostAction::class)->execute($this->thread, auth()->user(), [
            'content' => $this->replyContent,
            'parent_post_id' => $this->replyingToPostId,
        ]);

        $this->cancelReply();
    }

    public function toggleLike(int $postId): void
    {
        $post = ForumPost::findOrFail($postId);

        $this->authorize('like', $post);

        app(ToggleForumPostLikeAction::class)->execute($post, auth()->user());
    }

    public function render()
    {
        return view('livewire.student.forum-thread-show', [
            'posts' => $this->posts(),
        ]);
    }
}
