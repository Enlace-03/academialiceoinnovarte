{{--
    Progreso agregado por campo de pensamiento (Hito 4b): una barra por
    campo con datos, mismo estilo visual que la barra de proyecto individual
    ya existente (project-show.blade.php) -- sin eje ni comparación numérica
    entre campos, cada barra es independiente. Compartido entre portal de
    estudiante, dashboard de acudiente (una vez por hijo) y el modal de solo
    lectura de /academia -- por eso vive en resources/views/components, ya
    escaneado tanto por el build de Vite del portal como por el tema propio
    del panel académico (ver theme.css del hito anterior).

    $fields: Collection<array{thinkingField: ThinkingField, progressPct: int}>
    ya filtrada por AggregateThinkingFieldProgressAction -- un campo sin
    proyectos que lo toquen simplemente no aparece acá, nunca se sintetiza
    un 0% en la vista.

    $useStars (Hito de estrellas): false por defecto a propósito -- el call
    site de /academia (StudentProgressRelationManager, modal de solo lectura)
    nunca lo pasa, así que sigue viendo la barra numérica sin ningún cambio,
    para cualquier estudiante. Los call sites del portal (PortalHome,
    MyProjects) sí lo pasan, calculado una sola vez vía User::isInEarlyCycle()
    -- nunca ambas representaciones a la vez, ver <x-progress-stars>.
--}}
@props(['fields', 'useStars' => false])

@if ($fields->isNotEmpty())
    <div class="space-y-4">
        @foreach ($fields as $entry)
            <div>
                @if ($useStars)
                    <div class="flex items-center justify-between text-sm mb-1">
                        <span class="font-medium text-gray-700">{{ $entry['thinkingField']->name }}</span>
                    </div>
                    <x-progress-stars :percent="$entry['progressPct']" />
                @else
                    <div class="flex items-center justify-between text-sm mb-1">
                        <span class="font-medium text-gray-700">{{ $entry['thinkingField']->name }}</span>
                        <span class="text-gray-500">{{ $entry['progressPct'] }}%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2.5">
                        <div class="bg-emerald-500 h-2.5 rounded-full transition-all" style="width: {{ $entry['progressPct'] }}%"></div>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
@endif
