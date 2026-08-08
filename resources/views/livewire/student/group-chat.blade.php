<div>
    <h1 class="text-xl font-semibold mb-6">Chat de mi grupo</h1>

    @if ($group === null)
        <p class="text-sm text-gray-500">
            Todavía no tienes un grupo asignado. Habla con tu docente o secretaría.
        </p>
    @else
        <div wire:poll.5s class="bg-white rounded-lg shadow p-4 space-y-3 max-h-[28rem] overflow-y-auto">
            @forelse ($messages as $chatMessage)
                <div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium">{{ $chatMessage->user->name }}</span>
                        <span class="text-xs text-gray-400">{{ $chatMessage->created_at->diffForHumans() }}</span>
                    </div>
                    <p class="text-sm text-gray-700">{{ $chatMessage->content }}</p>
                </div>
            @empty
                <p class="text-sm text-gray-500">Todavía no hay mensajes en el chat de tu grupo.</p>
            @endforelse
        </div>

        <form wire:submit="send" class="mt-4 flex gap-2">
            <input type="text" wire:model="content" class="flex-1 border border-gray-300 rounded px-3 py-2 text-sm" placeholder="Escribe un mensaje...">
            <button type="submit" class="bg-emerald-600 text-white rounded px-4 py-2 text-sm font-medium hover:bg-emerald-700">
                Enviar
            </button>
        </form>
        @error('content') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
    @endif
</div>
