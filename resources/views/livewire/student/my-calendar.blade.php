<div>
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-semibold">Calendario de entregas</h1>
        <a href="{{ route('student.projects.index') }}" wire:navigate class="text-sm text-emerald-700 hover:underline">
            &larr; Mis proyectos
        </a>
    </div>

    {{-- Dos columnas en pantallas grandes (mismo patrón 2:1 que ProjectShow):
         calendario a la izquierda, actividades del mes (o del día
         seleccionado) a la derecha -- en mobile (grid-cols-1) la columna
         lateral cae debajo del calendario, mismo orden de siempre. --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        <div class="lg:col-span-2 bg-white rounded-lg shadow p-4">
            <div class="flex items-center justify-between mb-4">
                <button type="button" wire:click="previousMonth" class="text-sm text-gray-600 hover:text-emerald-700">
                    &larr; Anterior
                </button>
                <span class="font-medium capitalize">{{ $monthStart->translatedFormat('F Y') }}</span>
                <button type="button" wire:click="nextMonth" class="text-sm text-gray-600 hover:text-emerald-700">
                    Siguiente &rarr;
                </button>
            </div>

            <div class="grid grid-cols-7 gap-1 text-center text-xs text-gray-400 mb-1">
                <span>Lun</span>
                <span>Mar</span>
                <span>Mié</span>
                <span>Jue</span>
                <span>Vie</span>
                <span>Sáb</span>
                <span>Dom</span>
            </div>

            <div class="grid grid-cols-7 gap-1">
                @php
                    $gridStart = $monthStart->copy()->startOfWeek();
                    $gridEnd = $monthStart->copy()->endOfMonth()->endOfWeek();
                    $today = \Illuminate\Support\Carbon::today()->toDateString();
                    $day = $gridStart->copy();
                @endphp
                @while ($day->lte($gridEnd))
                    @php
                        $dateKey = $day->toDateString();
                        $inMonth = $day->month === $monthStart->month;
                        $entries = $pendingByDate->get($dateKey);
                        $hasOverdue = $entries?->contains(fn ($entry) => $entry['due_date']->isPast()) ?? false;
                        $hasUpcoming = $entries?->contains(fn ($entry) => ! $entry['due_date']->isPast()) ?? false;
                    @endphp
                    <button
                        type="button"
                        wire:click="selectDate('{{ $dateKey }}')"
                        @class([
                            'aspect-square rounded p-1 text-xs flex flex-col items-center justify-center gap-1 transition-colors',
                            'text-gray-300' => ! $inMonth,
                            'text-gray-700 hover:bg-gray-50' => $inMonth,
                            'bg-emerald-50' => $selectedDate === $dateKey,
                            'ring-1 ring-emerald-400' => $dateKey === $today,
                        ])
                    >
                        <span>{{ $day->day }}</span>
                        @if ($entries && $entries->isNotEmpty())
                            <span class="flex gap-0.5">
                                @if ($hasOverdue)
                                    <span class="w-1.5 h-1.5 rounded-full bg-red-500" aria-label="Entrega vencida"></span>
                                @endif
                                @if ($hasUpcoming)
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500" aria-label="Entrega próxima"></span>
                                @endif
                            </span>
                        @endif
                    </button>
                    @php $day->addDay(); @endphp
                @endwhile
            </div>
        </div>

        <div class="space-y-4">
            {{-- Leyenda de colores (pedido explícito: los puntos del grid del
                 calendario son muy chicos para explicar qué significan por sí
                 solos -- única explicación ahora, la leyenda chica que vivía
                 bajo el grid se quitó para no duplicarla). Siempre visible,
                 no depende de que el mes tenga entregas o no. --}}
            <div class="bg-white rounded-lg shadow p-4">
                <h2 class="text-sm font-medium text-gray-700 mb-3">¿Qué significan los colores?</h2>
                <div class="space-y-2 text-sm">
                    <div class="flex items-start gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-red-500 mt-1 shrink-0"></span>
                        <span class="text-gray-600"><span class="font-medium text-gray-700">Vencida</span> — la fecha límite ya pasó y todavía no entregaste.</span>
                    </div>
                    <div class="flex items-start gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-amber-500 mt-1 shrink-0"></span>
                        <span class="text-gray-600"><span class="font-medium text-gray-700">Próxima</span> — todavía tienes tiempo para entregar.</span>
                    </div>
                </div>
            </div>

            {{-- displayedEntries ya resuelve el default (todo el mes) vs. un día
                 puntual del lado del componente -- acá solo se decide si se
                 oculta por completo (vacío) o se lista. --}}
            @if ($displayedEntries->isNotEmpty())
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="text-sm font-medium text-gray-700">
                            @if ($selectedDate)
                                {{ \Illuminate\Support\Carbon::parse($selectedDate)->translatedFormat('d \d\e F') }}
                            @else
                                Entregas de {{ $monthStart->translatedFormat('F') }}
                            @endif
                        </h2>

                        @if ($selectedDate)
                            <button type="button" wire:click="selectDate('{{ $selectedDate }}')" class="text-xs text-emerald-700 hover:underline whitespace-nowrap">
                                Ver todo el mes
                            </button>
                        @endif
                    </div>

                    <ul class="divide-y divide-gray-100">
                        @foreach ($displayedEntries as $entry)
                            <li>
                                <a href="{{ route('student.evidence.show', ['project' => $entry['project'], 'evidence' => $entry['evidence']]) }}"
                                   wire:navigate
                                   class="flex items-start gap-2 -mx-2 px-2 py-2 rounded hover:bg-gray-50 transition-colors">
                                    <span
                                        @class([
                                            'w-2 h-2 rounded-full mt-1.5 shrink-0',
                                            'bg-red-500' => $entry['due_date']->isPast(),
                                            'bg-amber-500' => ! $entry['due_date']->isPast(),
                                        ])
                                        aria-label="{{ $entry['due_date']->isPast() ? 'Entrega vencida' : 'Entrega próxima' }}"
                                    ></span>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="text-sm text-gray-700">{{ $entry['evidence']->description }}</span>
                                            <span class="text-xs text-gray-400 whitespace-nowrap">{{ $entry['due_date']->format('d/m') }}</span>
                                        </div>
                                        <span class="text-xs text-gray-400 block">{{ $entry['phase_name'] }} — {{ $entry['project']->title }}</span>
                                    </div>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>
</div>
