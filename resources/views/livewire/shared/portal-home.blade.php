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
                    <h2 class="font-medium text-gray-900">{{ $entry['child']->name }}</h2>

                    @if ($entry['thinkingFieldProgress']->isNotEmpty())
                        <div class="mt-3 mb-4">
                            <h3 class="text-xs font-semibold uppercase text-gray-400 mb-2">Avance por campo de pensamiento</h3>
                            <x-thinking-field-progress :fields="$entry['thinkingFieldProgress']" />
                        </div>
                    @endif

                    @if ($entry['pending']->isEmpty())
                        <p class="text-sm text-gray-500 mt-2">Sin entregas pendientes por ahora.</p>
                    @else
                        <ul class="mt-2 divide-y divide-gray-100">
                            @foreach ($entry['pending'] as $pending)
                                <li class="py-2 text-sm flex items-center justify-between gap-4">
                                    <span class="text-gray-700">
                                        {{ $pending['description'] }}
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
