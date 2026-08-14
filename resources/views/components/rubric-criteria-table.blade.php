{{--
    Compartido entre EvidenceShow (estudiante) y el Placeholder de solo
    lectura del docente en evaluateSubmissionsAction() -- mismo componente,
    mismo criterio de resaltado, para que ambos lados vean exactamente la
    misma información desglosada (pedido explícito).

    $resultsByCriterion: array<int, ?RubricLevel> keyed por rubric_criterion_id.
    Un criterio ausente del array (entrega sin evaluar, o evaluación
    parcial) resuelve a null vía `?? null` -- ninguna celda se resalta para
    ese criterio, sin error. Colapsado por defecto (<details> nativo, sin
    JS): el resumen de la fila ya muestra el nivel logrado sin necesidad de
    expandir. Sin lógica de auto-expandir el criterio más bajo -- ver
    TODO.md, queda anotado como refinamiento futuro.
--}}
@props(['criteria', 'resultsByCriterion' => []])

@php
    $levelLabels = [
        'inicio' => 'Inicio',
        'en_proceso' => 'En proceso',
        'logro_esperado' => 'Logro esperado',
        'logro_destacado' => 'Logro destacado',
    ];
    $levelColorClasses = [
        'inicio' => 'bg-red-100 text-red-700',
        'en_proceso' => 'bg-orange-100 text-orange-700',
        'logro_esperado' => 'bg-amber-100 text-amber-700',
        'logro_destacado' => 'bg-green-100 text-green-700',
    ];
@endphp

<div class="space-y-2">
    @foreach ($criteria as $criterion)
        @php $achievedLevel = $resultsByCriterion[$criterion->id] ?? null; @endphp
        <details class="border border-gray-100 rounded">
            <summary class="cursor-pointer flex items-center justify-between gap-2 p-3 text-sm font-medium">
                <span>{{ $criterion->name }}</span>
                @if ($achievedLevel)
                    <span @class(['inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium shrink-0', $levelColorClasses[$achievedLevel->key] ?? 'bg-gray-100 text-gray-500'])>
                        {{ $achievedLevel->label }}
                    </span>
                @else
                    <span class="text-xs text-gray-400 shrink-0">Sin evaluar</span>
                @endif
            </summary>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 p-3 pt-0">
                @foreach ($levelLabels as $key => $label)
                    @php $isAchieved = $achievedLevel?->key === $key; @endphp
                    <div class="text-xs rounded p-2 {{ $isAchieved ? ($levelColorClasses[$key] ?? 'bg-gray-100 text-gray-700') : 'bg-gray-50 text-gray-600' }}">
                        <p class="font-medium mb-1">{{ $label }}</p>
                        <p>{{ $criterion->level_descriptions[$key] ?? '—' }}</p>
                    </div>
                @endforeach
            </div>
        </details>
    @endforeach
</div>
