<x-filament-panels::page>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1 space-y-4">
            <div class="fi-section rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Proyecto</label>
                <select
                    wire:model.live="projectId"
                    class="mt-1 w-full rounded-lg border-gray-300 text-sm dark:bg-gray-800 dark:border-gray-700"
                >
                    <option value="">Selecciona un proyecto...</option>
                    @foreach ($this->projects() as $project)
                        <option value="{{ $project->id }}" @selected($projectId === $project->id)>{{ $project->title }}</option>
                    @endforeach
                </select>
            </div>

            @if ($projectId !== null)
                <div class="fi-section rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <h3 class="text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">Conversaciones</h3>

                    @forelse ($this->threads() as $thread)
                        <button
                            type="button"
                            wire:click="selectThread({{ $thread->id }})"
                            @class([
                                'w-full text-left rounded-lg px-3 py-2 text-sm mb-1 transition-colors',
                                'bg-primary-50 text-primary-700 dark:bg-primary-500/10' => $threadId === $thread->id,
                                'hover:bg-gray-50 dark:hover:bg-white/5' => $threadId !== $thread->id,
                            ])
                        >
                            <span class="font-medium">
                                {{ $thread->type === 'individual' ? ($thread->student?->name ?? '—') : ($thread->team?->name ?? '—') }}
                            </span>
                            <span class="block text-xs text-gray-400">
                                {{ $thread->type === 'individual' ? 'Individual' : 'Equipo' }} · {{ $thread->messages_count }} mensajes
                            </span>
                        </button>
                    @empty
                        <p class="text-sm text-gray-500">Sin conversaciones en este proyecto todavía.</p>
                    @endforelse
                </div>
            @endif
        </div>

        <div class="lg:col-span-2">
            @if ($this->selectedThread())
                @php $thread = $this->selectedThread(); @endphp
                <livewire:shared.private-chat-panel
                    :project="$thread->project"
                    :type="$thread->type"
                    :student="$thread->student"
                    :team="$thread->team"
                    :key="'institutional-chat-'.$thread->id"
                />
            @else
                <div class="fi-section rounded-xl bg-white p-8 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 text-center text-gray-400">
                    <p class="text-sm">Selecciona un proyecto y una conversación para leerla.</p>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
