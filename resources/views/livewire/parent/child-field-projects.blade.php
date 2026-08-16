@php
    // Mismo catálogo estable que project-show.blade.php (skill rubric-evaluation).
    $levelColorClasses = [
        'inicio' => 'bg-red-100 text-red-700',
        'en_proceso' => 'bg-orange-100 text-orange-700',
        'logro_esperado' => 'bg-amber-100 text-amber-700',
        'logro_destacado' => 'bg-green-100 text-green-700',
    ];
@endphp

<div>
    <a href="{{ route('parent.child.fields', $child) }}" wire:navigate class="text-sm text-emerald-700 hover:underline">
        &larr; Campos de pensamiento de {{ $child->name }}
    </a>

    <h1 class="text-xl font-semibold mt-2">{{ $field->name }}</h1>

    @php $useStars = $child->isInEarlyCycle(); @endphp

    @if ($projects->isEmpty())
        <p class="text-sm text-gray-500 mt-4">
            Todavía no hay proyectos publicados que toquen este campo para {{ $child->name }}.
        </p>
    @else
        <div class="mt-6 space-y-3">
            @foreach ($projects as $project)
                @php $summary = $this->progressSummary($project); @endphp
                <a href="{{ route('parent.child.project.show', ['child' => $child, 'project' => $project]) }}"
                   wire:navigate
                   class="block bg-white p-4 rounded-lg shadow hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <h2 class="font-medium">{{ $project->title }}</h2>
                            <p class="text-sm text-gray-500 mt-1">{{ $project->guiding_question }}</p>
                        </div>
                        @if ($summary['level'])
                            <span @class(['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium whitespace-nowrap', $levelColorClasses[$summary['level']->key] ?? 'bg-gray-100 text-gray-500'])>
                                {{ $summary['level']->label }}
                            </span>
                        @endif
                    </div>

                    <div class="mt-3">
                        @if ($useStars)
                            <div class="text-xs text-gray-500 mb-1">Avance</div>
                            <x-progress-stars :percent="$summary['pct']" />
                        @else
                            <div class="flex items-center justify-between text-xs text-gray-500 mb-1">
                                <span>Avance</span>
                                <span>{{ $summary['pct'] }}%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-emerald-500 h-2 rounded-full transition-all" style="width: {{ $summary['pct'] }}%"></div>
                            </div>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
