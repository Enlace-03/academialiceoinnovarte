@php
    // rubric_levels.color guarda un hex (#EF4444...), no un nombre de color
    // Filament — se mapea por 'key', que es el catálogo estable documentado
    // en el skill rubric-evaluation.
    $levelColorClasses = [
        'inicio' => 'bg-red-100 text-red-700',
        'en_proceso' => 'bg-orange-100 text-orange-700',
        'logro_esperado' => 'bg-amber-100 text-amber-700',
        'logro_destacado' => 'bg-green-100 text-green-700',
    ];
@endphp

<div>
    <a href="{{ route('student.projects.index') }}" wire:navigate class="text-sm text-emerald-700 hover:underline">
        &larr; Mis proyectos
    </a>

    <h1 class="text-xl font-semibold mt-2">{{ $project->title }}</h1>
    <p class="text-sm text-gray-600 mt-1">{{ $project->guiding_question }}</p>

    <div class="mt-8 space-y-8">
        @foreach ($phases as $phase)
            <section class="bg-white rounded-lg shadow p-5">
                <h2 class="font-semibold text-emerald-800">{{ $phase->name }}</h2>

                @if ($phase->description)
                    <p class="text-sm text-gray-600 mt-1">{{ $phase->description }}</p>
                @endif

                {{-- Guías --}}
                @if ($phase->guides->isNotEmpty())
                    <div class="mt-4 space-y-3">
                        <h3 class="text-xs font-semibold uppercase text-gray-400">Guías</h3>
                        @foreach ($phase->guides as $guide)
                            <div class="border border-gray-100 rounded p-3">
                                <p class="font-medium text-sm">{{ $guide->title }}</p>
                                @if ($guide->content)
                                    <p class="text-sm text-gray-600 mt-1">{{ $guide->content }}</p>
                                @endif

                                @if ($guide->resources->isNotEmpty())
                                    <ul class="mt-2 space-y-1">
                                        @foreach ($guide->resources as $resource)
                                            <li class="text-sm">
                                                <a href="{{ $resource->url_or_path }}" target="_blank" class="text-emerald-700 hover:underline">
                                                    {{ $resource->title }}
                                                </a>
                                                <span class="text-xs text-gray-400">({{ \App\Modules\Project\Models\Resource::TYPES[$resource->type] ?? $resource->type }})</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Recursos generales de la fase --}}
                @if ($phase->resources->isNotEmpty())
                    <div class="mt-4">
                        <h3 class="text-xs font-semibold uppercase text-gray-400">Recursos</h3>
                        <ul class="mt-2 space-y-1">
                            @foreach ($phase->resources as $resource)
                                <li class="text-sm">
                                    <a href="{{ $resource->url_or_path }}" target="_blank" class="text-emerald-700 hover:underline">
                                        {{ $resource->title }}
                                    </a>
                                    <span class="text-xs text-gray-400">({{ \App\Modules\Project\Models\Resource::TYPES[$resource->type] ?? $resource->type }})</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Evidencias esperadas --}}
                @if ($phase->expectedEvidences->isNotEmpty())
                    <div class="mt-4">
                        <h3 class="text-xs font-semibold uppercase text-gray-400">Evidencias esperadas</h3>
                        <div class="mt-2 space-y-2">
                            @foreach ($phase->expectedEvidences as $evidence)
                                @php $evidenceStatus = $this->evidenceStatus($evidence); @endphp
                                <div class="flex items-start justify-between gap-4 border border-gray-100 rounded p-3">
                                    <div>
                                        <p class="text-sm font-medium">
                                            {{ \App\Modules\Project\Models\ExpectedEvidence::TYPES[$evidence->type] ?? $evidence->type }}
                                        </p>
                                        <p class="text-sm text-gray-600">{{ $evidence->description }}</p>

                                        @if ($evidenceStatus['status'] === 'evaluada')
                                            @if ($evidenceStatus['feedback'])
                                                <p class="text-sm text-gray-600 mt-2 italic">"{{ $evidenceStatus['feedback'] }}"</p>
                                            @endif
                                        @endif
                                    </div>

                                    <div class="text-right whitespace-nowrap">
                                        @if ($evidenceStatus['status'] === 'pendiente')
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">
                                                Pendiente
                                            </span>
                                        @elseif ($evidenceStatus['status'] === 'entregada')
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
                                                Entregada
                                            </span>
                                        @elseif ($evidenceStatus['level'])
                                            <span @class([
                                                'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                                                $levelColorClasses[$evidenceStatus['level']->key] ?? 'bg-gray-100 text-gray-500',
                                            ])>
                                                {{ $evidenceStatus['level']->label }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </section>
        @endforeach
    </div>

    <div class="mt-8">
        <livewire:student.forum-thread-list :project="$project" />
    </div>
</div>
