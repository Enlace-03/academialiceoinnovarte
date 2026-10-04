{{--
    Bienvenida (Figma "Plataforma Innovartec", frame "Inicio" 8:3). Vista
    Blade pura sin lógica: solo la ven invitados (ruta 'welcome', middleware
    guest). Los botones "Modelo pedagógico" y "Nuestro propósito" y los
    íconos de redes quedan sin destino hasta que exista el contenido.
--}}
@php
    $img = fn (string $file) => asset('images/innovartec/'.$file);

    // TODO: reemplazar '#' por las URLs reales de las redes del colegio.
    $socials = [
        ['label' => 'Instagram', 'icon' => 'social-instagram.svg', 'url' => '#'],
        ['label' => 'Facebook', 'icon' => 'social-facebook.svg', 'url' => '#'],
        ['label' => 'TikTok', 'icon' => 'social-tiktok.svg', 'url' => '#'],
        ['label' => 'Sitio web', 'icon' => 'social-web.svg', 'url' => '#'],
    ];
@endphp

<x-layouts.guest title="Bienvenida — InnovArTec" body-class="bg-innov-bg">
    <div class="mx-auto flex min-h-screen w-full max-w-7xl flex-col px-6 py-8 sm:px-12 lg:px-20">
        <header class="flex flex-col gap-6 sm:flex-row sm:items-start sm:justify-between">
            <nav class="flex flex-col gap-5" aria-label="Información del colegio">
                <button type="button" class="flex h-[86px] w-[260px] items-center justify-center gap-2.5 rounded-[20px] bg-innov-lilac p-2.5 text-left opacity-70 drop-shadow-[4px_4px_2px_rgba(0,0,0,0.25)]">
                    <img src="{{ $img('icon-modelo.svg') }}" alt="" class="h-[49px] w-[42px] shrink-0">
                    <span class="text-[23px] font-bold leading-5 text-innov-purple">Modelo<br>pedagógico</span>
                    <img src="{{ $img('icon-chevron-modelo.svg') }}" alt="" class="ml-auto h-[30px] w-[29px] shrink-0">
                </button>

                <button type="button" class="flex h-[86px] w-[260px] items-center justify-center gap-2.5 rounded-[20px] bg-innov-orange-soft p-2.5 text-left drop-shadow-[4px_4px_2px_rgba(0,0,0,0.25)]">
                    <img src="{{ $img('icon-proposito.svg') }}" alt="" class="size-[51px] shrink-0">
                    <span class="text-[23px] font-bold leading-5 text-innov-orange">Nuestro<br>propósito</span>
                    <img src="{{ $img('icon-chevron-proposito.svg') }}" alt="" class="ml-auto h-[30px] w-[29px] shrink-0">
                </button>
            </nav>

            <ul class="flex gap-1.5 sm:pt-5" aria-label="Redes del colegio">
                @foreach ($socials as $social)
                    <li>
                        <a href="{{ $social['url'] }}" aria-label="{{ $social['label'] }}" target="_blank" rel="noopener noreferrer">
                            <img src="{{ $img($social['icon']) }}" alt="" class="size-[38px]">
                        </a>
                    </li>
                @endforeach
            </ul>
        </header>

        <main class="flex flex-1 flex-col items-center justify-center gap-10 py-8">
            <img src="{{ $img('logo-completo.png') }}" alt="InnovArTec" class="h-auto w-full max-w-[676px] object-contain">

            <a href="{{ route('login') }}" class="flex h-[71px] w-full max-w-[538px] items-center justify-center rounded-[20px] bg-innov-green p-2.5 text-[28px] font-extrabold text-innov-green-soft transition hover:brightness-95 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-innov-green">
                Iniciar sesión
            </a>
        </main>
    </div>
</x-layouts.guest>
