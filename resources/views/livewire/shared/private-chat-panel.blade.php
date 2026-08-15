<div class="bg-white rounded-lg shadow p-5">
    <h3 class="font-semibold text-emerald-800 mb-3">{{ $this->heading() }}</h3>

    <div wire:poll.5s class="space-y-3 max-h-[24rem] overflow-y-auto">
        @forelse ($messages as $chatMessage)
            <div @class(['rounded p-2', 'bg-gray-50 opacity-60' => $chatMessage->is_hidden])>
                <div class="flex items-center justify-between gap-2">
                    <span class="text-sm font-medium">{{ $chatMessage->user->name }}</span>
                    <div class="flex items-center gap-2 shrink-0">
                        @if ($chatMessage->is_hidden)
                            <span class="text-xs text-gray-400">oculto por {{ $chatMessage->hiddenBy?->name ?? 'moderación' }}</span>
                        @endif
                        <span class="text-xs text-gray-400">{{ $chatMessage->created_at->diffForHumans() }}</span>
                        @if ($canModerate && ! $chatMessage->is_hidden)
                            <button
                                type="button"
                                wire:click="hide({{ $chatMessage->id }})"
                                wire:confirm="¿Ocultar este mensaje?"
                                class="text-xs text-red-600 hover:underline"
                            >
                                Ocultar
                            </button>
                        @endif
                    </div>
                </div>
                <p class="text-sm text-gray-700">{{ $chatMessage->content }}</p>
            </div>
        @empty
            <p class="text-sm text-gray-500">
                Todavía no hay mensajes en esta conversación.
                @if ($canSend) Envía el primero. @endif
            </p>
        @endforelse
    </div>

    @if ($canSend)
        <form wire:submit="send" class="mt-4 flex gap-2">
            <input type="text" wire:model="content" class="flex-1 border border-gray-300 rounded px-3 py-2 text-sm" placeholder="Escribe un mensaje...">
            <button type="submit" class="bg-emerald-600 text-white rounded px-4 py-2 text-sm font-medium hover:bg-emerald-700">
                Enviar
            </button>
        </form>
        @error('content') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
    @endif
</div>
