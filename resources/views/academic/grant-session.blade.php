<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Entregar sesión — Academia Liceo Innovarte</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 min-h-screen text-gray-900">
    <header class="bg-white border-b border-gray-200 px-4 py-3">
        <span class="font-semibold">Academia Liceo Innovarte</span>
    </header>

    <main class="max-w-md mx-auto p-6">
        <a href="{{ route('filament.academic.resources.groups.index') }}" class="text-sm text-emerald-700 hover:underline">
            &larr; Grupos
        </a>

        <div class="bg-white p-6 rounded-lg shadow mt-4">
            <h1 class="text-lg font-semibold mb-1">Entregar sesión</h1>
            <p class="text-sm text-gray-500 mb-4">{{ $group->name }}</p>

            @if ($students->isEmpty())
                <p class="text-sm text-gray-500">Este grupo no tiene estudiantes matriculados.</p>
            @else
                <form method="POST" action="{{ route('academic.group-sessions.store', $group) }}">
                    @csrf

                    <label class="block text-sm font-medium mb-1">Estudiante</label>
                    <select id="student_id" name="student_id" required class="w-full border border-gray-300 rounded px-3 py-2 mb-4">
                        <option value="">Selecciona un estudiante</option>
                        @foreach ($students as $student)
                            <option value="{{ $student->id }}">{{ $student->name }}</option>
                        @endforeach
                    </select>

                    <p id="confirmation-text" class="text-sm text-gray-600 mb-4">
                        Selecciona un estudiante para continuar.
                    </p>

                    <button type="submit" class="w-full bg-emerald-600 text-white rounded py-2 font-medium hover:bg-emerald-700">
                        Confirmar y entregar
                    </button>
                </form>

                <script>
                    // JS vanilla a propósito -- sin Livewire/Alpine en este paso (ver
                    // docblock de la ruta academic.group-sessions.create en
                    // routes/web.php: nada de framework en el paso que cambia de
                    // identidad). Solo lee el texto de la opción seleccionada, ya
                    // presente en el DOM -- no hace ninguna petición.
                    document.getElementById('student_id').addEventListener('change', function (event) {
                        var select = event.target;
                        var text = document.getElementById('confirmation-text');

                        if (! select.value) {
                            text.textContent = 'Selecciona un estudiante para continuar.';
                            return;
                        }

                        var studentName = select.options[select.selectedIndex].text;
                        text.textContent = 'Vas a ceder el control de este dispositivo a ' + studentName + '. Tu sesión en /academia se cerrará en este mismo dispositivo.';
                    });
                </script>
            @endif
        </div>
    </main>
</body>
</html>
