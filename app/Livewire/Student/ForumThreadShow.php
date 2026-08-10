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
use Livewire\WithFileUploads;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Respuestas de un solo nivel: replyingToPostId solo puede apuntar a un post
 * raíz (parent_post_id null) — el botón "responder" ni siquiera se muestra
 * para una respuesta (ver vista), y CreateForumPostAction rechaza el intento
 * de todas formas si se fuerza por wire:click manipulado.
 *
 * El conteo de likes es público para quien ve el post; la lista de quién dio
 * like NUNCA se carga aquí (ForumPostPolicy::viewLikers es solo personal).
 *
 * hasRole('student') explícito (Hito 3b-2, mismo patrón que GroupChat):
 * válido sin ambigüedad porque el mecanismo de entrega de sesión es
 * auth-switch real (Auth::loginUsingId) -- auth()->user() durante una
 * sesión entregada ES el estudiante, no hay "delegación" que distinguir.
 */
#[Layout('layouts.portal')]
class ForumThreadShow extends Component
{
    use WithFileUploads;

    /**
     * KB, antes de comprimir -- solo frontera de entrada del usuario (ver
     * CreateForumPostAction para por qué el límite de CANTIDAD de fotos, en
     * cambio, vive del lado del Action).
     */
    private const MAX_PHOTO_KB = 8192;

    public Project $project;

    public ForumThread $thread;

    #[Validate('required|string|max:2000')]
    public string $newPostContent = '';

    /** @var array<\Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $newPostPhotos = [];

    public ?int $replyingToPostId = null;

    #[Validate('required|string|max:2000')]
    public string $replyContent = '';

    public function mount(Project $project, ForumThread $thread): void
    {
        abort_unless(auth()->user()->hasRole('student'), 403);

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

        $this->validate([
            'newPostContent' => 'required|string|max:2000',
            'newPostPhotos' => 'array|max:'.CreateForumPostAction::MAX_PHOTOS_PER_POST,
            'newPostPhotos.*' => 'image|max:'.self::MAX_PHOTO_KB,
        ]);

        app(CreateForumPostAction::class)->execute($this->thread, auth()->user(), [
            'content' => $this->newPostContent,
            'photos' => $this->newPostPhotos,
        ]);

        $this->newPostContent = '';
        $this->newPostPhotos = [];
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
