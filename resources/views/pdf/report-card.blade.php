<!DOCTYPE html>
{{--
    Boletín en PDF (dompdf). Solo tablas y estilos inline básicos: sin
    flexbox/grid (dompdf no los soporta) y sin imágenes ni recursos remotos
    (enable_remote está en false). DejaVu Sans para que las tildes salgan bien.
    Los números 1-5 solo existen en este documento (regla #4: nunca en la UI).
--}}
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} — {{ $studentName }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111827; }
        h1 { font-size: 18px; margin: 0 0 4px 0; }
        h2 { font-size: 13px; margin: 22px 0 6px 0; }
        p { margin: 2px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #9ca3af; padding: 5px 6px; text-align: left; }
        th { background-color: #e5e7eb; }
        td.num { text-align: center; width: 70px; }
        .muted { color: #6b7280; }
    </style>
</head>
<body>
    <h1>{{ $institution }}</h1>
    <p><strong>{{ $title }}</strong> — Año lectivo {{ $academicYear }}</p>
    <p>Estudiante: <strong>{{ $studentName }}</strong></p>
    @if ($gradeName)
        <p>Grado: {{ $gradeName }}</p>
    @endif
    <p class="muted">Generado el {{ $generatedAt }}</p>

    <h2>Nivel por campo de pensamiento</h2>
    @if (count($fields) === 0)
        <p class="muted">Sin evaluaciones registradas para este año lectivo.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Campo de pensamiento</th>
                    <th>Nivel</th>
                    <th class="num">Valoración</th>
                    <th>Desempeño</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($fields as $row)
                    <tr>
                        <td>{{ $row['name'] }}</td>
                        <td>{{ $row['level'] }}</td>
                        <td class="num">{{ $row['number'] }}</td>
                        <td>{{ $row['performance'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Nivel por proyecto</h2>
    @if (count($projects) === 0)
        <p class="muted">Sin evaluaciones registradas para este año lectivo.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Proyecto</th>
                    <th>Nivel</th>
                    <th class="num">Valoración</th>
                    <th>Desempeño</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($projects as $row)
                    <tr>
                        <td>{{ $row['title'] }}</td>
                        <td>{{ $row['level'] }}</td>
                        <td class="num">{{ $row['number'] }}</td>
                        <td>{{ $row['performance'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p class="muted" style="margin-top: 18px;">
        Escala de valoración: 2 Bajo · 3 Básico · 4 Alto · 5 Superior.
    </p>
</body>
</html>
