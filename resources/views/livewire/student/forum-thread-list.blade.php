<div class="bg-white rounded-lg shadow p-5">
    <div class="flex items-center justify-between">
        <h2 class="font-semibold">Foro</h2>
        <button type="button" wire:click="$toggle('showForm')" class="text-sm text-emerald-700 hover:underline">
            {{ $showForm ? 'Cancelar' : '+ Nuevo hilo' }}
        </button>
    </div>

    @if ($showForm)
        <form wire:submit="createThread" class="mt-4 space-y-3 border border-gray-100 rounded p-3">
            <div>
                <label class="block text-sm font-medium mb-1">Título</label>
                <input type="text" wire:model="newThreadTitle" class="w-full border border-gray-300 rounded px-3 py-2">
                @error('newThreadTitle') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">Fase (opcional)</label>
                <select wire:model="newThreadPhaseId" class="w-full border border-gray-300 rounded px-3 py-2">
                    <option value="">General del proyecto</option>
                    @foreach ($phases as $phase)
                        <option value="{{ $phase->id }}">{{ $phase->name }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="bg-emerald-600 text-white rounded px-4 py-2 text-sm font-medium hover:bg-emerald-700">
                Crear hilo
            </button>
        </form>
    @endif

    <div class="mt-4 space-y-2">
        @forelse ($threads as $thread)
            <a href="{{ route('student.forum.show', ['project' => $project->uuid, 'thread' => $thread->uuid]) }}" wire:navigate
               class="block border border-gray-100 rounded p-3 hover:bg-gray-50">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium">{{ $thread->title }}</p>
                        @if ($thread->phase)
                            <span class="text-xs text-gray-400">{{ $thread->phase->name }}</span>
                        @endif
                    </div>
                    <span class="text-xs text-gray-400 whitespace-nowrap">{{ $thread->posts_count }} respuestas</span>
                </div>
            </a>
        @empty
            <p class="text-sm text-gray-500">Todavía no hay hilos en este proyecto.</p>
        @endforelse
    </div>
</div>
