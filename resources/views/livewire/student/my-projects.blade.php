<div>
    <h1 class="text-xl font-semibold mb-6">Mis proyectos</h1>

    <div class="bg-white rounded-lg shadow p-4 mb-6">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-sm font-medium text-gray-700">Próxima entrega</h2>
            <a href="{{ route('student.calendar') }}" wire:navigate class="text-xs font-medium text-emerald-700 hover:underline">
                Ver calendario
            </a>
        </div>

        @if ($pendingEvidences->isEmpty())
            <p class="text-sm text-gray-500">No tienes ningún trabajo que entregar en los próximos 7 días.</p>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach ($pendingEvidences as $entry)
                    <li>
                        <a href="{{ route('student.evidence.show', ['project' => $entry['project'], 'evidence' => $entry['evidence']]) }}"
                           wire:navigate
                           class="flex items-center justify-between gap-4 -mx-2 px-2 py-2 rounded hover:bg-gray-50 transition-colors">
                            <span class="text-sm text-gray-700">
                                {{ $entry['evidence']->description }}
                                <span class="text-gray-400">— {{ $entry['phase_name'] }}</span>
                            </span>
                            <span class="text-xs text-gray-500 whitespace-nowrap">{{ $entry['due_date']->format('d/m/Y') }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    @if ($thinkingFieldProgress->isNotEmpty())
        <div class="bg-white rounded-lg shadow p-4 mb-6">
            <h2 class="text-sm font-medium text-gray-700 mb-4">Avance por campo de pensamiento</h2>
            <x-thinking-field-progress :fields="$thinkingFieldProgress" :use-stars="auth()->user()->isInEarlyCycle()" />
        </div>
    @endif

    @if ($projects->isEmpty())
        <p class="text-sm text-gray-500">
            Todavía no hay proyectos disponibles para tu ciclo.
        </p>
    @else
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach ($projects as $project)
                @php $progressPct = $progressByProject->get($project->id)?->progress_pct ?? 0; @endphp
                <a href="{{ route('student.projects.show', $project->uuid) }}" wire:navigate
                   class="block bg-white p-4 rounded-lg shadow hover:shadow-md transition-shadow">
                    <div class="flex items-start justify-between gap-4 mb-1">
                        <h2 class="font-medium">{{ $project->title }}</h2>
                        <span class="text-xs text-gray-400 whitespace-nowrap">
                            {{ $project->year }} · Semestre {{ $project->semester }}
                        </span>
                    </div>
                    <p class="text-sm text-gray-500 mb-1">{{ $project->guiding_question }}</p>
                    @if ($project->createdBy)
                        <p class="text-xs text-gray-400 mb-3">Docente: {{ $project->createdBy->name }}</p>
                    @endif

                    @if (auth()->user()->isInEarlyCycle())
                        <x-progress-stars :percent="$progressPct" />
                    @else
                        <div class="flex items-center justify-between text-xs mb-1">
                            <span class="text-gray-500">Avance</span>
                            <span class="text-gray-500">{{ $progressPct }}%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-emerald-500 h-2 rounded-full" style="width: {{ $progressPct }}%"></div>
                        </div>
                    @endif
                </a>
            @endforeach
        </div>
    @endif
</div>
