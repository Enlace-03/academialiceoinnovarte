<?php

declare(strict_types=1);

namespace App\Modules\Assessment\Support;

use InvalidArgumentException;

/**
 * Conversión de salida del nivel cualitativo de una rúbrica a la escala
 * numérica entera 1-5 del boletín (confirmada por Rafa; tabla confirmada por
 * Diego). Método puro: sin acceso a BD ni a modelos, recibe la `key` del
 * nivel (rubric_levels.key). Solo para el documento oficial del boletín --
 * la regla de oro (nunca mostrar números de nivel en la UI) sigue vigente.
 *
 * | key              | Nivel            | Número | Desempeño                              |
 * |------------------|------------------|--------|----------------------------------------|
 * | (ninguno)        | —                | 1      | Estudiante ausente o fuera del proceso |
 * | inicio           | Inicio           | 2      | Bajo                                   |
 * | en_proceso       | En proceso       | 3      | Básico                                 |
 * | logro_esperado   | Logro esperado   | 4      | Alto                                   |
 * | logro_destacado  | Logro destacado  | 5      | Superior                               |
 *
 * El 1 NO corresponde a ningún nivel de la rúbrica: es ABSENT, un caso
 * especial que quien genera el boletín decide por otra vía. Ningún nivel se
 * convierte en 1, y una key desconocida lanza excepción en vez de adivinar
 * un número que iría a un documento oficial.
 */
final class ReportCardScale
{
    /** Estudiante ausente o fuera del proceso evaluativo; ningún RubricLevel lo produce. */
    public const ABSENT = 1;

    private const BY_LEVEL_KEY = [
        'inicio' => 2,
        'en_proceso' => 3,
        'logro_esperado' => 4,
        'logro_destacado' => 5,
    ];

    public static function fromLevelKey(string $levelKey): int
    {
        return self::BY_LEVEL_KEY[$levelKey]
            ?? throw new InvalidArgumentException("Nivel de rúbrica desconocido para la escala del boletín: '{$levelKey}'.");
    }
}
