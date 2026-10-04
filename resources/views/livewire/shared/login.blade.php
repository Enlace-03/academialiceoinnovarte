{{--
    Login (Figma "Plataforma Innovartec", frame "Inicio" 31:563). Mismo
    formulario y mismas propiedades de siempre (email/password/errorMessage);
    solo cambia la presentación. El campo "Usuario" del diseño es el correo.
--}}
@php
    $img = fn (string $file) => asset('images/innovartec/'.$file);

    $categories = [
        ['label' => 'Crea', 'icon' => 'icon-crea.svg', 'class' => 'bg-innov-purple-soft border-innov-purple-border'],
        ['label' => 'Aprende', 'icon' => 'icon-aprende.svg', 'class' => 'bg-innov-green-soft border-innov-green'],
        ['label' => 'Explora', 'icon' => 'icon-explora.svg', 'class' => 'bg-innov-blue-soft border-innov-blue'],
        ['label' => 'Expresa', 'icon' => 'icon-expresa.svg', 'class' => 'bg-innov-yellow-soft border-innov-orange'],
    ];

    $features = [
        ['text' => 'Entorno seguro', 'text2' => 'y confiable', 'icon' => 'icon-seguro.svg'],
        ['text' => 'Contenido educativo', 'text2' => 'de calidad', 'icon' => 'icon-educacion.svg'],
        ['text' => 'Herramientas', 'text2' => 'que inspiran', 'icon' => 'icon-estrella.svg'],
    ];

    // TODO: mismas URLs reales que en welcome.blade.php.
    $socials = [
        ['label' => 'Instagram', 'icon' => 'social-instagram.svg', 'url' => '#'],
        ['label' => 'Facebook', 'icon' => 'social-facebook.svg', 'url' => '#'],
        ['label' => 'TikTok', 'icon' => 'social-tiktok.svg', 'url' => '#'],
        ['label' => 'Sitio web', 'icon' => 'social-web.svg', 'url' => '#'],
    ];

    $inputClass = 'h-[67px] w-full rounded-[5px] border border-innov-lilac bg-innov-bg pl-[56px] text-base text-innov-ink shadow-[inset_0_4px_4px_rgba(0,0,0,0.25)] focus:border-innov-purple focus:outline-none focus:ring-2 focus:ring-innov-purple/40';
@endphp

<div class="mx-auto grid min-h-screen w-full max-w-7xl items-center gap-10 px-6 py-8 sm:px-12 lg:grid-cols-[minmax(0,1fr)_447px] lg:px-16">
    {{-- Columna izquierda: marca y mensajes (solo escritorio) --}}
    <section class="hidden lg:block" aria-hidden="false">
        <div class="relative h-[98px] w-full max-w-[585px] overflow-hidden">
            <img src="{{ $img('logo-completo.png') }}" alt="InnovArTec" class="absolute left-0 top-[-465.2%] h-[577.46%] w-full max-w-none">
        </div>
        <p class="mt-5 text-[32px] font-bold leading-tight text-innov-tagline">Tu espacio para aprender, crear y descubrir</p>

        <div class="relative mt-10">
            <div class="max-w-[393px]">
                <p class="text-2xl">Explora tus clases, actividades y recursos mientras desarrollas todo tu potencial creativo.</p>

                <ul class="mt-10 flex gap-[17px]">
                    @foreach ($categories as $category)
                        <li class="flex w-[83px] flex-col items-center gap-2">
                            <span class="flex size-[83px] items-center justify-center rounded-[20px] border {{ $category['class'] }}">
                                <img src="{{ $img($category['icon']) }}" alt="" class="size-[54px]">
                            </span>
                            <span class="text-base">{{ $category['label'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <img src="{{ $img('mascotas.png') }}" alt="" class="pointer-events-none absolute left-[345px] top-[-8px] h-[444px] w-[507px] max-w-none rotate-[2.54deg] object-contain xl:left-[360px]">
        </div>

        <div class="mt-10 flex w-[383px] items-center gap-2.5 rounded-[10px] bg-innov-purple-soft p-2.5">
            <img src="{{ $img('icon-corazon.svg') }}" alt="" class="size-[87px] shrink-0">
            <p class="text-2xl leading-snug text-innov-purple">En nuestro Liceo cada idea puede convertirse en algo increíble.</p>
        </div>

        <ul class="mt-9 flex flex-wrap gap-3">
            @foreach ($features as $feature)
                <li class="flex h-[77px] items-center gap-2.5 rounded-[10px] bg-white p-2.5 drop-shadow-[4px_4px_2px_rgba(0,0,0,0.25)]">
                    <img src="{{ $img($feature['icon']) }}" alt="" class="size-[35px] shrink-0">
                    <span class="text-xl leading-tight text-innov-purple">{{ $feature['text'] }}<br>{{ $feature['text2'] }}</span>
                </li>
            @endforeach
        </ul>

        <ul class="mt-10 flex gap-1.5" aria-label="Redes del colegio">
            @foreach ($socials as $social)
                <li>
                    <a href="{{ $social['url'] }}" aria-label="{{ $social['label'] }}" target="_blank" rel="noopener noreferrer">
                        <img src="{{ $img($social['icon']) }}" alt="" class="size-[38px]">
                    </a>
                </li>
            @endforeach
        </ul>
    </section>

    {{-- Columna derecha: tarjeta de login --}}
    <section class="mx-auto w-full max-w-[447px] rounded-[27px] bg-white px-[30px] pb-10 pt-8 shadow-[4px_4px_4px_rgba(0,0,0,0.25)]">
        <div class="relative mx-auto h-[153px] w-[126px] overflow-hidden">
            <img src="{{ $img('logo-completo.png') }}" alt="InnovArTec" class="absolute left-[-32.26%] top-[-2.85%] h-[126.45%] w-[159.35%] max-w-none">
        </div>

        <h1 class="mt-2 text-center font-display text-[40px] leading-tight text-innov-title">¡Que alegría verte!</h1>
        <p class="mt-1 text-center text-xl font-medium">Ingresa para continuar aprendiendo</p>

        @if ($errorMessage)
            <div role="alert" class="mt-5 rounded border border-red-200 bg-red-50 p-2 text-sm text-red-600">
                {{ $errorMessage }}
            </div>
        @endif

        <form wire:submit="login" class="mt-6 space-y-5" x-data="{ showPassword: false, forgot: false }">
            <div>
                <label for="login-email" class="mb-1 block text-base font-semibold italic">Usuario</label>
                <div class="relative">
                    <img src="{{ $img('icon-usuario.svg') }}" alt="" class="pointer-events-none absolute left-3 top-1/2 size-[34px] -translate-y-1/2">
                    <input id="login-email" type="email" wire:model="email" autocomplete="username" placeholder="Tu correo" class="{{ $inputClass }} pr-4">
                </div>
                @error('email') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </div>

            <div>
                <label for="login-password" class="mb-1 block text-base font-semibold italic">Contraseña</label>
                <div class="relative">
                    <img src="{{ $img('icon-candado.svg') }}" alt="" class="pointer-events-none absolute left-3 top-1/2 size-[34px] -translate-y-1/2">
                    <input id="login-password" wire:model="password" autocomplete="current-password" x-bind:type="showPassword ? 'text' : 'password'" type="password" class="{{ $inputClass }} pr-14">
                    <button type="button" x-on:click="showPassword = ! showPassword" x-bind:aria-label="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'" class="absolute right-4 top-1/2 -translate-y-1/2">
                        <img src="{{ $img('icon-ojo.svg') }}" alt="" class="size-[29px]" x-bind:class="showPassword ? 'opacity-50' : ''">
                    </button>
                </div>
                @error('password') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </div>

            <button type="submit" wire:loading.attr="disabled" class="flex h-[47px] w-full items-center justify-center rounded-[5px] bg-innov-green text-xl font-extrabold text-innov-green-soft transition hover:brightness-95 disabled:opacity-60">
                Iniciar sesión
            </button>

            {{-- Sin recuperación automática (TODO.md): la contraseña la restablece secretaría desde /admin. --}}
            <div class="text-center">
                <button type="button" x-on:click="forgot = ! forgot" class="text-base font-semibold italic text-innov-purple">Olvidé mi contraseña</button>
                <p x-show="forgot" x-cloak class="mt-2 text-sm text-innov-ink">Comunícate con secretaría para restablecerla.</p>
            </div>
        </form>
    </section>
</div>
