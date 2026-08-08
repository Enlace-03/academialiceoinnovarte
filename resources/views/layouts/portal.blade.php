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
            <span class="font-semibold">Academia Liceo Innovarte</span>
            <div class="flex items-center gap-4">
                <span class="text-sm text-gray-600">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-sm text-red-600 hover:underline">
                        Cerrar sesión
                    </button>
                </form>
            </div>
        </header>
    @endauth

    <main class="max-w-3xl mx-auto p-6">
        {{ $slot }}
    </main>

    @livewireScripts
</body>
</html>
