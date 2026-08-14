@php
    // Mismo catálogo estable que project-show.blade.php (skill
    // rubric-evaluation) -- rubric_levels.key, no el 'order' numérico.
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

<div>
    <a href="{{ route('student.projects.show', $project) }}" wire:navigate class="text-sm text-emerald-700 hover:underline">
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
                    <div class="space-y-4">
                        @foreach ($evidence->rubric->criteria as $criterion)
                            <div class="border border-gray-100 rounded p-3">
                                <p class="text-sm font-medium">{{ $criterion->name }}</p>
                                <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    @foreach ($levelLabels as $key => $label)
                                        <div class="text-xs">
                                            <span @class(['inline-block px-1.5 py-0.5 rounded font-medium mb-1', $levelColorClasses[$key]])>
                                                {{ $label }}
                                            </span>
                                            <p class="text-gray-600">{{ $criterion->level_descriptions[$key] ?? '—' }}</p>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- Columna derecha: estado + adjuntos + entrega --}}
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
            @elseif ($evidenceState['status'] === 'devuelta' && ! $editing)
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

            @if ($editing)
                {{-- Formulario de entrega/reentrega --}}
                <div class="space-y-4">
                    <div>
                        <label class="text-xs font-semibold uppercase text-gray-400">Comentario (opcional)</label>
                        <textarea wire:model="textContent" rows="3" class="mt-1 w-full rounded border-gray-300 text-sm"></textarea>
                        @error('textContent') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <h3 class="text-xs font-semibold uppercase text-gray-400 mb-2">Adjuntos</h3>

                        <div class="space-y-2">
                            @foreach ($keptExisting as $existing)
                                <div class="flex items-center justify-between gap-2 border border-gray-100 rounded p-2 text-sm">
                                    <div class="min-w-0 flex-1">
                                        @if ($existing['type'] === 'photo')
                                            <span class="truncate">📎 {{ $existing['original_filename'] ?? 'Foto' }}</span>
                                        @else
                                            <x-youtube-embed :url="$existing['url']" />
                                        @endif
                                    </div>
                                    <button type="button" wire:click="removeExisting({{ $existing['id'] }})" class="text-xs text-red-600 hover:underline shrink-0">Quitar</button>
                                </div>
                            @endforeach

                            @foreach ($newPhotos as $index => $photo)
                                <div class="flex items-center justify-between gap-2 border border-gray-100 rounded p-2 text-sm">
                                    <span class="min-w-0 flex-1 truncate">📎 {{ $photo->getClientOriginalName() }}</span>
                                    <button type="button" wire:click="removeNewPhoto({{ $index }})" class="text-xs text-red-600 hover:underline shrink-0">Quitar</button>
                                </div>
                            @endforeach

                            @foreach ($newLinks as $index => $link)
                                <div class="flex items-center justify-between gap-2 border border-gray-100 rounded p-2 text-sm">
                                    <div class="min-w-0 flex-1">
                                        <x-youtube-embed :url="$link['url']" />
                                    </div>
                                    <button type="button" wire:click="removeNewLink({{ $index }})" class="text-xs text-red-600 hover:underline shrink-0">Quitar</button>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <label class="text-xs px-3 py-1.5 rounded border border-gray-300 cursor-pointer hover:bg-gray-50">
                                Agregar foto
                                <input type="file" wire:model="newPhotos" multiple accept="image/*" class="hidden">
                            </label>

                            <div class="flex items-center gap-1">
                                <input type="url" wire:model="linkInput" placeholder="https://..." class="text-xs rounded border-gray-300 py-1.5">
                                <button type="button" wire:click="addLink" class="text-xs px-3 py-1.5 rounded border border-gray-300 hover:bg-gray-50">Agregar enlace</button>
                            </div>
                        </div>
                        @error('newPhotos.*') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        @error('linkInput') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        @error('attachments') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <button type="button" wire:click="submit" wire:loading.attr="disabled" class="w-full bg-emerald-600 text-white text-sm font-medium py-2 rounded hover:bg-emerald-700 disabled:opacity-50">
                        Entregar
                    </button>
                </div>
            @elseif ($submission)
                {{-- Vista de solo lectura de lo ya entregado --}}
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

                @if ($evidenceState['status'] === 'devuelta')
                    <button type="button" wire:click="startResubmission" class="mt-4 w-full bg-orange-600 text-white text-sm font-medium py-2 rounded hover:bg-orange-700">
                        Volver a entregar
                    </button>
                @endif
            @endif
        </div>
    </div>

    {{-- Chat privado docente-estudiante: hito aparte, espacio reservado --}}
    <div class="mt-6 bg-white rounded-lg shadow p-5 text-center text-gray-400">
        <p class="text-sm font-medium">💬 Chat con el docente</p>
        <p class="text-xs mt-1">Próximamente</p>
    </div>
</div>
