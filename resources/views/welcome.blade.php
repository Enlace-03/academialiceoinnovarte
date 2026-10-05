{{--
    Bienvenida (Figma "Plataforma Innovartec", frame "Inicio" 8:3). Vista
    Blade pura sin lógica: solo la ven invitados (ruta 'welcome', middleware
    guest). Los botones "Modelo pedagógico" y "Nuestro propósito" y los
    íconos de redes quedan sin destino hasta que exista el contenido.
--}}
@php
    $img = fn (string $file) => asset('images/innovartec/'.$file);

    $socials = array_filter(config('innovartec.socials'), fn (array $social) => filled($social['url']));
@endphp

<x-layouts.guest title="Bienvenida — InnovArTec" body-class="bg-innov-bg">
    <div class="mx-auto relative flex min-h-dvh w-full max-w-7xl flex-col px-6 py-6 sm:px-12 lg:px-20 xl:h-dvh overflow-x-clip xl:overflow-hidden">
        <header class="flex flex-col gap-6 sm:flex-row sm:items-start sm:justify-between xl:absolute xl:inset-x-20 xl:top-6 xl:z-10">
            <nav class="flex flex-col gap-5 sm:flex-row xl:flex-col" aria-label="Información del colegio">
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
                        <a href="{{ $social['url'] }}" aria-label="{{ $social['label'] }}" target="_blank" rel="noopener noreferrer" class="group block">
                            <img src="{{ $img($social['icon']) }}" alt="" class="size-[38px] transition duration-200 group-hover:scale-90 group-hover:brightness-75 group-focus-visible:scale-90 group-focus-visible:brightness-75">
                        </a>
                    </li>
                @endforeach
            </ul>
        </header>

        <main class="flex min-h-0 flex-1 flex-col items-center justify-center gap-[3vh] py-4">
            {{-- El SVG hace la entrada en cascada y al final se funde con el PNG original,
                 que conserva los degradados y detalles finos (ver app.css, .logo-anim). --}}
            <div class="relative min-h-[260px] w-full max-w-[676px] flex-1 xl:min-h-0 2xl:max-w-[860px] min-[1900px]:max-w-[1100px]">
                <x-innovartec-logo class="absolute inset-0 size-full" />
                <img src="{{ $img('logo-completo.png') }}" alt="InnovArTec" class="logo-final absolute inset-0 size-full object-contain">
                <div class="logo-flash pointer-events-none absolute -inset-[8%]" aria-hidden="true"></div>
            </div>

            <a href="{{ route('login') }}" class="flex h-[71px] w-full max-w-[538px] shrink-0 items-center justify-center rounded-[20px] bg-innov-green p-2.5 text-[28px] font-extrabold text-white transition hover:bg-[#6fa11a] hover:shadow-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-innov-green">
                Iniciar sesión
            </a>
        </main>
    </div>
</x-layouts.guest>
