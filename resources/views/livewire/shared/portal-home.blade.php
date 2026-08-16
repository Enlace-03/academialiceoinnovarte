<div>
    <h1 class="text-xl font-semibold">Bienvenido, {{ auth()->user()->name }}</h1>

    @role('student')
        <a href="{{ route('student.projects.index') }}" wire:navigate
           class="inline-block mt-4 bg-emerald-600 text-white rounded px-4 py-2 text-sm font-medium hover:bg-emerald-700">
            Ver mis proyectos
        </a>
    @endrole

    @role('parent')
        <div class="mt-6 space-y-6">
            @forelse ($childrenDashboard as $entry)
                <div class="bg-white border border-gray-200 rounded p-4">
                    <div class="flex items-center gap-4">
                        <div class="shrink-0 w-16 h-16 rounded-full overflow-hidden">
                            @if ($entry['child']->hasPhoto())
                                <img src="{{ route('students.photo.show', $entry['child']) }}" alt="" class="w-16 h-16 object-cover">
                            @else
                                <img src="{{ asset('images/generic-student-avatar.svg') }}" alt="" class="w-16 h-16">
                            @endif
                        </div>
                        <div>
                            <h2 class="font-medium text-gray-900">{{ $entry['child']->name }}</h2>
                            <p class="text-xs text-gray-500">{{ $entry['child']->schoolGrade?->cycle?->name }}</p>
                        </div>
                    </div>

                    @if ($entry['canManagePhoto'])
                        <div class="mt-2 text-sm">
                            @if ($entry['child']->photo_upload_blocked)
                                <p class="text-xs text-gray-400">La subida de foto está deshabilitada para este estudiante.</p>
                            @else
                                <div class="flex items-center gap-3" wire:loading.class="opacity-50" wire:target="uploadPhoto({{ $entry['child']->id }})">
                                    <label class="text-emerald-600 text-xs font-medium cursor-pointer hover:underline">
                                        {{ $entry['child']->hasPhoto() ? 'Cambiar foto' : 'Agregar foto' }}
                                        <input
                                            type="file"
                                            wire:model="photoUploads.{{ $entry['child']->id }}"
                                            accept="image/*"
                                            class="hidden"
                                        >
                                    </label>

                                    {{-- El archivo ya quedó subido a almacenamiento temporal en cuanto
                                         Livewire sincroniza la propiedad (wire:model en un input file
                                         sube de inmediato, sin necesitar .live) -- este botón solo
                                         dispara el paso de negocio real (comprimir + guardar en el
                                         estudiante), evitando cualquier condición de carrera entre la
                                         subida async y una acción auto-disparada por el evento nativo. --}}
                                    @if ($photoUploads[$entry['child']->id] ?? null)
                                        <button
                                            type="button"
                                            wire:click="uploadPhoto({{ $entry['child']->id }})"
                                            class="text-emerald-700 text-xs font-semibold hover:underline"
                                        >
                                            Guardar foto
                                        </button>
                                    @endif

                                    @if ($entry['child']->hasPhoto())
                                        <button
                                            type="button"
                                            wire:click="removePhoto({{ $entry['child']->id }})"
                                            wire:confirm="¿Quitar la foto de {{ $entry['child']->name }}?"
                                            class="text-red-600 text-xs font-medium hover:underline"
                                        >
                                            Quitar foto
                                        </button>
                                    @endif
                                </div>
                                @error('photoUploads.' . $entry['child']->id)
                                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                                @enderror
                            @endif
                        </div>
                    @endif

                    @if ($entry['thinkingFieldProgress']->isNotEmpty())
                        <a href="{{ route('parent.child.fields', $entry['child']) }}" wire:navigate
                           class="block mt-3 mb-4 -mx-2 px-2 py-1 rounded hover:bg-gray-50 transition-colors">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="text-xs font-semibold uppercase text-gray-400">Avance por campo de pensamiento</h3>
                                <span class="text-xs font-medium text-emerald-700">Ver detalle &rarr;</span>
                            </div>
                            <x-thinking-field-progress :fields="$entry['thinkingFieldProgress']" :use-stars="$entry['child']->isInEarlyCycle()" />
                        </a>
                    @endif

                    @if ($entry['pending']->isEmpty())
                        <p class="text-sm text-gray-500 mt-2">Sin entregas pendientes por ahora.</p>
                    @else
                        <ul class="mt-2 divide-y divide-gray-100">
                            @foreach ($entry['pending'] as $pending)
                                <li class="py-2 text-sm flex items-center justify-between gap-4">
                                    <span class="text-gray-700">
                                        {{ $pending['evidence']->description }}
                                        <span class="text-gray-400">— {{ $pending['phase_name'] }}</span>
                                    </span>
                                    <span class="text-gray-500 whitespace-nowrap">{{ $pending['due_date']->format('d/m/Y') }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @empty
                <p class="text-sm text-gray-500">No tienes estudiantes vinculados todavía.</p>
            @endforelse
        </div>
    @endrole
</div>
