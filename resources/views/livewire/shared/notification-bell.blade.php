<div class="relative" wire:poll.30s>
    <button wire:click="toggle" class="relative text-gray-600 hover:text-emerald-700" aria-label="Notificaciones">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
        </svg>
        @if ($unreadCount > 0)
            <span class="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] leading-none rounded-full h-4 w-4 flex items-center justify-center">
                {{ $unreadCount > 9 ? '9+' : $unreadCount }}
            </span>
        @endif
    </button>

    @if ($open)
        <div class="absolute right-0 mt-2 w-80 bg-white border border-gray-200 rounded shadow-lg z-10">
            <div class="flex items-center justify-between px-3 py-2 border-b border-gray-100">
                <span class="text-sm font-medium">Notificaciones</span>
                @if ($unreadCount > 0)
                    <button wire:click="markAllAsRead" class="text-xs text-emerald-700 hover:underline">Marcar todas como leídas</button>
                @endif
            </div>

            <div class="max-h-96 overflow-y-auto divide-y divide-gray-100">
                @forelse ($notifications as $notification)
                    <button
                        wire:click="markAsRead('{{ $notification->id }}')"
                        class="w-full text-left px-3 py-2 text-sm {{ $notification->read_at ? 'text-gray-500' : 'text-gray-900 bg-emerald-50' }} hover:bg-gray-50"
                    >
                        @php $data = $notification->data; @endphp
                        @switch($data['type'] ?? null)
                            @case('submission_deadline_reminder')
                                Tu entrega de la fase "{{ $data['phase_name'] }}" vence en {{ $data['threshold_days'] }} día(s).
                                @break
                            @case('forum_reply_received')
                                {{ $data['author_name'] }} respondió a tu publicación en el foro.
                                @break
                            @case('evaluation_received')
                                {{ $data['teacher_name'] }} evaluó tu entrega en "{{ $data['project_title'] }}". Nivel: {{ $data['level_label'] ?? 'sin nivel asignado' }}.
                                @break
                            @default
                                Tienes una notificación nueva.
                        @endswitch

                        <div class="text-[11px] text-gray-400 mt-0.5">{{ $notification->created_at->diffForHumans() }}</div>
                    </button>
                @empty
                    <p class="px-3 py-4 text-sm text-gray-400 text-center">Sin notificaciones todavía.</p>
                @endforelse
            </div>
        </div>
    @endif
</div>
