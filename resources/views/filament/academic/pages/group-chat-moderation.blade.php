<x-filament-panels::page>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1">
            <div class="fi-section rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Grupo</label>
                <select
                    wire:model.live="groupId"
                    class="mt-1 w-full rounded-lg border-gray-300 text-sm dark:bg-gray-800 dark:border-gray-700"
                >
                    <option value="">Selecciona un grupo...</option>
                    @foreach ($this->groups() as $group)
                        <option value="{{ $group->id }}" @selected($groupId === $group->id)>
                            {{ $group->cycle?->name }} - {{ $group->name }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="lg:col-span-2">
            @if ($groupId === null)
                <div class="fi-section rounded-xl bg-white p-8 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 text-center text-gray-400">
                    <p class="text-sm">Selecciona un grupo para ver su chat.</p>
                </div>
            @else
                <div class="fi-section rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 space-y-3 max-h-[32rem] overflow-y-auto">
                    @forelse ($this->messages() as $message)
                        <div @class([
                            'border rounded-lg p-3 text-sm',
                            'border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950/30' => $message->is_hidden,
                            'border-gray-100 dark:border-white/10' => ! $message->is_hidden,
                        ])>
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-medium text-gray-700 dark:text-gray-200">{{ $message->user?->name ?? '—' }}</span>
                                <span class="text-xs text-gray-400">{{ $message->created_at->format('d/m/Y H:i') }}</span>
                            </div>

                            <p class="mt-1 text-gray-600 dark:text-gray-300">{{ $message->content }}</p>

                            @if ($message->is_hidden)
                                <span class="mt-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300">
                                    Oculto
                                </span>
                            @else
                                <button
                                    type="button"
                                    wire:click="hide({{ $message->id }})"
                                    wire:confirm="¿Ocultar este mensaje?"
                                    class="mt-2 text-xs text-red-600 hover:underline"
                                >
                                    Ocultar
                                </button>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Sin mensajes en este grupo todavía.</p>
                    @endforelse
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
