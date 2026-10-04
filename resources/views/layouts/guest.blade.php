<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'InnovArTec — Liceo Innovarte' }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="{{ $bodyClass ?? 'bg-innov-cream' }} min-h-screen font-body text-innov-ink antialiased">
    {{ $slot }}

    {{--
        Layout de las pantallas SIN sesión (bienvenida y login). Replica el
        script de guardado del #fragmento de layouts/portal.blade.php: el
        navegador nunca envía el fragmento al servidor, así que se guarda en
        sessionStorage al llegar a /login para que el portal lo restaure
        después del redirect (ver comentario completo en layouts/portal).
    --}}
    @if (request()->routeIs('login'))
        <script>
            if (window.location.hash) {
                sessionStorage.setItem('liceo_scroll_hash', window.location.hash);
            }
        </script>
    @endif

    @livewireScripts
</body>
</html>
