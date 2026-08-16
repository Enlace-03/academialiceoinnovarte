<div>
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-semibold">Calendario de entregas</h1>
        <a href="{{ route('student.projects.index') }}" wire:navigate class="text-sm text-emerald-700 hover:underline">
            &larr; Mis proyectos
        </a>
    </div>

    <div class="bg-white rounded-lg shadow p-4">
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

        <div class="flex items-center gap-4 mt-4 text-xs text-gray-500">
            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-red-500"></span> Vencida</span>
            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-amber-500"></span> Próxima</span>
        </div>
    </div>

    @if ($selectedEntries !== null)
        <div class="mt-4 bg-white rounded-lg shadow p-4">
            <h2 class="text-sm font-medium text-gray-700 mb-3">
                {{ \Illuminate\Support\Carbon::parse($selectedDate)->translatedFormat('d \d\e F') }}
            </h2>

            @if ($selectedEntries->isEmpty())
                <p class="text-sm text-gray-500">Sin entregas pendientes ese día.</p>
            @else
                <ul class="divide-y divide-gray-100">
                    @foreach ($selectedEntries as $entry)
                        <li>
                            <a href="{{ route('student.evidence.show', ['project' => $entry['project'], 'evidence' => $entry['evidence']]) }}"
                               wire:navigate
                               class="block -mx-2 px-2 py-2 rounded hover:bg-gray-50 transition-colors">
                                <span class="text-sm text-gray-700">{{ $entry['evidence']->description }}</span>
                                <span class="text-xs text-gray-400 block">{{ $entry['phase_name'] }} — {{ $entry['project']->title }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif
</div>
