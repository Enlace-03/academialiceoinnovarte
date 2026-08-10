<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Academia Liceo Innovarte' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-gray-50 min-h-screen text-gray-900">
    @auth
        <header class="bg-white border-b border-gray-200 px-4 py-3 flex items-center justify-between">
            <div class="flex items-center gap-6">
                <span class="font-semibold">Academia Liceo Innovarte</span>

                @role('student')
                    <nav class="flex items-center gap-4 text-sm">
                        <a href="{{ route('student.projects.index') }}" wire:navigate class="text-gray-600 hover:text-emerald-700">Mis proyectos</a>
                        <a href="{{ route('student.chat') }}" wire:navigate class="text-gray-600 hover:text-emerald-700">Chat de mi grupo</a>
                        <a href="{{ route('portal.gallery') }}" wire:navigate class="text-gray-600 hover:text-emerald-700">Galería</a>
                    </nav>
                @endrole

                @role('parent')
                    <nav class="flex items-center gap-4 text-sm">
                        <a href="{{ route('portal.gallery') }}" wire:navigate class="text-gray-600 hover:text-emerald-700">Galería</a>
                    </nav>
                @endrole
            </div>
            <div class="flex items-center gap-4">
                <livewire:shared.notification-bell />
                <span class="text-sm text-gray-600">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-sm text-red-600 hover:underline">
                        {{ session('active_grant_id') ? 'Terminar clase' : 'Cerrar sesión' }}
                    </button>
                </form>
            </div>
        </header>
    @endauth

    <main class="max-w-3xl mx-auto p-6">
        {{ $slot }}
    </main>

    {{--
        Rescata el #fragmento (ej. #fase-4 de un enlace de notificación) que
        se pierde en el flujo de intended-redirect: el navegador nunca envía
        el fragmento al servidor (es puramente del lado del cliente), así
        que session('url.intended') jamás lo tiene -- Login::login() redirige
        correctamente a la URL, pero sin el fragmento, y el scroll nativo del
        navegador no tiene nada a qué saltar. Bug real encontrado en la
        verificación manual de este mismo flujo (segunda vuelta del Hito 5).

        Solo actúa en /login (guardar) y en cualquier otra página del layout
        (restaurar) -- nunca en una carga normal con fragmento ya presente en
        la URL, donde el scroll nativo del navegador ya funciona solo y no
        hay que interferir.
    --}}
    @if (request()->routeIs('login'))
        <script>
            if (window.location.hash) {
                sessionStorage.setItem('liceo_scroll_hash', window.location.hash);
            }
        </script>
    @else
        <script>
            (function () {
                const savedHash = sessionStorage.getItem('liceo_scroll_hash');

                if (! savedHash) {
                    return;
                }

                sessionStorage.removeItem('liceo_scroll_hash');

                const target = document.querySelector(savedHash);

                if (target) {
                    target.scrollIntoView();
                }
            })();
        </script>
    @endif

    @livewireScripts
</body>
</html>
