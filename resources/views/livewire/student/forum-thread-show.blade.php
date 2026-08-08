<div>
    <a href="{{ route('student.projects.show', $project->uuid) }}" wire:navigate class="text-sm text-emerald-700 hover:underline">
        &larr; {{ $project->title }}
    </a>

    <h1 class="text-xl font-semibold mt-2">{{ $thread->title }}</h1>
    @if ($thread->phase)
        <span class="text-xs text-gray-400">{{ $thread->phase->name }}</span>
    @endif

    <div class="mt-6 space-y-4">
        @forelse ($posts as $post)
            <div class="bg-white rounded-lg shadow p-4">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium">{{ $post->user->name }}</span>
                    <span class="text-xs text-gray-400">{{ $post->created_at->diffForHumans() }}</span>
                </div>
                <p class="text-sm text-gray-700 mt-2">{{ $post->content }}</p>

                <div class="mt-3 flex items-center gap-4 text-sm">
                    <button type="button" wire:click="toggleLike({{ $post->id }})" class="text-gray-500 hover:text-emerald-700">
                        &#9825; {{ $post->likes_count }}
                    </button>

                    <button type="button" wire:click="startReply({{ $post->id }})" class="text-emerald-700 hover:underline">
                        Responder
                    </button>
                </div>

                @if ($replyingToPostId === $post->id)
                    <form wire:submit="submitReply" class="mt-3 pl-4 border-l-2 border-gray-100">
                        <textarea wire:model="replyContent" rows="2" class="w-full border border-gray-300 rounded px-3 py-2 text-sm"></textarea>
                        @error('replyContent') <span class="text-xs text-red-600">{{ $message }}</span> @enderror

                        <div class="mt-2 flex gap-2">
                            <button type="submit" class="bg-emerald-600 text-white rounded px-3 py-1.5 text-sm font-medium hover:bg-emerald-700">
                                Responder
                            </button>
                            <button type="button" wire:click="cancelReply" class="text-sm text-gray-500 hover:underline">
                                Cancelar
                            </button>
                        </div>
                    </form>
                @endif

                {{-- Un solo nivel de respuesta: replies nunca muestra su propio botón "Responder". --}}
                @if ($post->replies->isNotEmpty())
                    <div class="mt-4 pl-4 border-l-2 border-gray-100 space-y-3">
                        @foreach ($post->replies as $reply)
                            <div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-medium">{{ $reply->user->name }}</span>
                                    <span class="text-xs text-gray-400">{{ $reply->created_at->diffForHumans() }}</span>
                                </div>
                                <p class="text-sm text-gray-700 mt-1">{{ $reply->content }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @empty
            <p class="text-sm text-gray-500">Todavía no hay publicaciones en este hilo.</p>
        @endforelse
    </div>

    <form wire:submit="createPost" class="mt-6 bg-white rounded-lg shadow p-4">
        <label class="block text-sm font-medium mb-1">Nueva publicación</label>
        <textarea wire:model="newPostContent" rows="3" class="w-full border border-gray-300 rounded px-3 py-2 text-sm"></textarea>
        @error('newPostContent') <span class="text-xs text-red-600">{{ $message }}</span> @enderror

        <button type="submit" class="mt-2 bg-emerald-600 text-white rounded px-4 py-2 text-sm font-medium hover:bg-emerald-700">
            Publicar
        </button>
    </form>
</div>
