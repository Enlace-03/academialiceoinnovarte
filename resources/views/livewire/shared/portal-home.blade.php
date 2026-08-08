<div>
    <h1 class="text-xl font-semibold">Bienvenido, {{ auth()->user()->name }}</h1>

    @role('student')
        <a href="{{ route('student.projects.index') }}" wire:navigate
           class="inline-block mt-4 bg-emerald-600 text-white rounded px-4 py-2 text-sm font-medium hover:bg-emerald-700">
            Ver mis proyectos
        </a>
    @endrole
</div>
