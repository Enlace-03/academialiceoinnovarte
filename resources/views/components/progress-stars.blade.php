{{--
    Reemplazo de la barra 0-100% para ciclos 1-2 (Exploratorio, Conceptual) --
    nunca conviven en la misma pantalla, ver User::isInEarlyCycle(). Nunca
    usado del lado de /academia (docente/personal siempre ve la barra
    numérica, sin excepción) -- este componente vive en resources/views/
    components porque los call sites del portal (estudiante y acudiente) lo
    necesitan, pero ningún Resource/RelationManager de Filament lo referencia.

    10 estrellas, cada una vale 10% de $percent. Relleno parcial (no
    redondeado a estrella completa) para la estrella que cae a mitad de
    camino -- mismo criterio de "no perder precisión real" que ya rige la
    barra numérica, solo expresado de forma más amigable para primaria/
    transición. Técnica de relleno parcial: estrella vacía de fondo +
    estrella llena superpuesta, recortada por ancho (0-100% del ancho de ESA
    estrella puntual, no del total) -- evita depender de un set de iconos
    con medias-estrellas pre-dibujadas.

    Sin texto de porcentaje junto a las estrellas a propósito: mostrar "60%"
    al lado de las estrellas sería la otra representación del mismo dato
    conviviendo con esta, justo lo que la decisión confirmada prohíbe. El
    porcentaje real sigue disponible para lectores de pantalla via aria-label.
--}}
@props(['percent'])

@php
    $clampedPercent = max(0, min(100, (int) round($percent)));
    $fullStars = intdiv($clampedPercent, 10);
    $partialStarFillPct = ($clampedPercent % 10) * 10;
    $starPath = 'M10 1.5l2.6 5.4 5.9.8-4.3 4.2 1 5.9L10 14.9l-5.2 2.9 1-5.9L1.5 7.7l5.9-.8L10 1.5z';
@endphp

<div {{ $attributes->merge(['class' => 'flex items-center gap-0.5']) }} role="img" aria-label="{{ $clampedPercent }}% de avance">
    @for ($i = 0; $i < 10; $i++)
        @php
            $fillPct = $i < $fullStars ? 100 : ($i === $fullStars ? $partialStarFillPct : 0);
        @endphp
        <span class="relative inline-block w-4 h-4 shrink-0">
            <svg viewBox="0 0 20 20" class="absolute inset-0 w-full h-full text-gray-300" fill="currentColor" aria-hidden="true">
                <path d="{{ $starPath }}" />
            </svg>
            @if ($fillPct > 0)
                <span class="absolute inset-0 overflow-hidden" style="width: {{ $fillPct }}%">
                    <svg viewBox="0 0 20 20" class="w-4 h-4 text-emerald-500" fill="currentColor" aria-hidden="true">
                        <path d="{{ $starPath }}" />
                    </svg>
                </span>
            @endif
        </span>
    @endfor
</div>
