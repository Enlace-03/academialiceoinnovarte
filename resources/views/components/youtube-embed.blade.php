{{--
    Reutilizable (Hito 3b-3): usado hoy en los adjuntos de entrega del
    estudiante y en el Placeholder de solo lectura del docente. Pensado
    también para el futuro hito de video en galería (TODO.md #26) -- misma
    detección (YoutubeUrlDetector), mismo iframe responsive.

    $url ya se asume detectado como YouTube por el caller (is_youtube=true
    guardado en SubmissionAttachment, o chequeo en vivo en el formulario) --
    este componente vuelve a resolver el embedUrl acá para no depender de que
    el caller lo haya guardado, y para poder usarse también sobre datos que
    todavía no se persistieron (previsualización antes de "Entregar").
--}}
@props(['url'])

@php
    $detection = \App\Modules\Shared\Support\YoutubeUrlDetector::detect($url);
@endphp

@if ($detection['isYoutube'])
    <div {{ $attributes->merge(['class' => 'relative w-full overflow-hidden rounded aspect-video bg-black']) }}>
        <iframe
            src="{{ $detection['embedUrl'] }}"
            class="absolute inset-0 h-full w-full"
            title="Video de YouTube"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
            allowfullscreen
            loading="lazy"
        ></iframe>
    </div>
@else
    <a href="{{ $url }}" target="_blank" rel="noopener" class="text-sm text-emerald-700 hover:underline break-all">
        {{ $url }}
    </a>
@endif
