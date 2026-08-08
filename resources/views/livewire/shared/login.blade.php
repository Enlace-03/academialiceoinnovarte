<div class="max-w-sm mx-auto mt-16 bg-white p-8 rounded-lg shadow">
    <h1 class="text-lg font-semibold mb-6 text-center">Academia Liceo Innovarte</h1>

    @if ($errorMessage)
        <div class="mb-4 text-sm text-red-600 bg-red-50 border border-red-200 rounded p-2">
            {{ $errorMessage }}
        </div>
    @endif

    <form wire:submit="login" class="space-y-4">
        <div>
            <label class="block text-sm font-medium mb-1">Correo electrónico</label>
            <input type="email" wire:model="email" class="w-full border border-gray-300 rounded px-3 py-2">
            @error('email') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Contraseña</label>
            <input type="password" wire:model="password" class="w-full border border-gray-300 rounded px-3 py-2">
            @error('password') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
        </div>

        <button type="submit" class="w-full bg-emerald-600 text-white rounded py-2 font-medium hover:bg-emerald-700">
            Entrar
        </button>
    </form>
</div>
