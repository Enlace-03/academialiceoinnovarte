<div>
    <a href="{{ route('portal.home') }}" wire:navigate class="text-sm text-emerald-700 hover:underline">
        &larr; Inicio
    </a>

    <h1 class="text-xl font-semibold mt-2">Campos de pensamiento de {{ $child->name }}</h1>

    @php $useStars = $child->isInEarlyCycle(); @endphp

    @if ($fields->isEmpty())
        <p class="text-sm text-gray-500 mt-4">
            Todavía no hay avance registrado en ningún campo de pensamiento para {{ $child->name }}.
        </p>
    @else
        <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
            @foreach ($fields as $entry)
                <a href="{{ route('parent.child.field.projects', ['child' => $child, 'field' => $entry['thinkingField']]) }}"
                   wire:navigate
                   class="block bg-white rounded-lg shadow p-4 hover:shadow-md transition-shadow">
                    @if ($useStars)
                        <div class="text-sm font-medium text-gray-700 mb-1">{{ $entry['thinkingField']->name }}</div>
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
                </a>
            @endforeach
        </div>
    @endif
</div>
