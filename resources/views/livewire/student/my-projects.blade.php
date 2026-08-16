<div>
    <h1 class="text-xl font-semibold mb-6">Mis proyectos</h1>

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
        <div class="space-y-3">
            @foreach ($projects as $project)
                <a href="{{ route('student.projects.show', $project->uuid) }}" wire:navigate
                   class="block bg-white p-4 rounded-lg shadow hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h2 class="font-medium">{{ $project->title }}</h2>
                            <p class="text-sm text-gray-500 mt-1">{{ $project->guiding_question }}</p>
                        </div>
                        <span class="text-xs text-gray-400 whitespace-nowrap">
                            {{ $project->year }} · Semestre {{ $project->semester }}
                        </span>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
