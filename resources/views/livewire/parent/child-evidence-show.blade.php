@php
    // Mismo catálogo estable que Student\EvidenceShow (skill rubric-evaluation).
    $levelColorClasses = [
        'inicio' => 'bg-red-100 text-red-700',
        'en_proceso' => 'bg-orange-100 text-orange-700',
        'logro_esperado' => 'bg-amber-100 text-amber-700',
        'logro_destacado' => 'bg-green-100 text-green-700',
    ];
@endphp

<div>
    <a href="{{ route('parent.child.project.show', ['child' => $child, 'project' => $project]) }}" wire:navigate class="text-sm text-emerald-700 hover:underline">
        &larr; {{ $project->title }}
    </a>

    <h1 class="text-xl font-semibold mt-2">
        {{ \App\Modules\Project\Models\ExpectedEvidence::TYPES[$evidence->type] ?? $evidence->type }}
    </h1>

    <div class="mt-6 grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
        {{-- Columna izquierda: instrucciones + rúbrica (solo lectura) --}}
        <div class="bg-white rounded-lg shadow p-5 space-y-4">
            <div>
                <h2 class="font-semibold text-emerald-800">{{ $evidence->phase->name }}</h2>
                @if ($evidence->phase->description)
                    <p class="text-sm text-gray-600 mt-1">{{ $evidence->phase->description }}</p>
                @endif
            </div>

            <div>
                <h3 class="text-xs font-semibold uppercase text-gray-400">Qué se espera entregar</h3>
                <p class="text-sm text-gray-700 mt-1">{{ $evidence->description }}</p>
            </div>

            @if ($evidence->rubric)
                <div>
                    <h3 class="text-xs font-semibold uppercase text-gray-400 mb-2">Rúbrica: {{ $evidence->rubric->name }}</h3>
                    <x-rubric-criteria-table :criteria="$evidence->rubric->criteria" :results-by-criterion="$resultsByCriterion" />
                </div>
            @endif
        </div>

        {{-- Columna derecha: estado + adjuntos ya entregados, solo lectura --}}
        <div class="bg-white rounded-lg shadow p-5">
            @if ($evidenceState['status'] === 'evaluada')
                <div class="mb-4">
                    <span @class(['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium', $levelColorClasses[$evidenceState['level']->key] ?? 'bg-gray-100 text-gray-500'])>
                        {{ $evidenceState['level']->label }}
                    </span>
                    @if ($evidenceState['feedback'])
                        <p class="text-sm text-gray-600 mt-2 italic">"{{ $evidenceState['feedback'] }}"</p>
                    @endif
                </div>
            @elseif ($evidenceState['status'] === 'devuelta')
                <div class="mb-4">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-700">
                        Devuelto para corregir
                    </span>
                    @if ($evidenceState['feedback'])
                        <p class="text-sm text-gray-600 mt-2 italic">"{{ $evidenceState['feedback'] }}"</p>
                    @endif
                </div>
            @elseif ($evidenceState['status'] === 'entregada')
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700 mb-4">
                    Entregada — en espera de evaluación
                </span>
            @endif

            @if ($submission)
                <div class="space-y-2">
                    @if ($submission->text_content)
                        <p class="text-sm text-gray-700">{{ $submission->text_content }}</p>
                    @endif

                    @foreach ($submission->attachments as $attachment)
                        <div class="border border-gray-100 rounded p-2 text-sm">
                            @if ($attachment->type === 'photo')
                                @if ($attachment->isImage())
                                    <a href="{{ route('submissions.attachments.show', $attachment) }}" target="_blank">
                                        <img src="{{ route('submissions.attachments.show', $attachment) }}" alt="{{ $attachment->original_filename }}" class="max-h-40 rounded">
                                    </a>
                                @else
                                    <a href="{{ route('submissions.attachments.show', $attachment) }}" target="_blank" class="text-emerald-700 hover:underline">
                                        📎 {{ $attachment->original_filename ?? 'Archivo' }}
                                    </a>
                                @endif
                            @else
                                <x-youtube-embed :url="$attachment->url" />
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-gray-500">{{ $child->name }} todavía no ha entregado esta evidencia.</p>
            @endif
        </div>
    </div>
</div>
