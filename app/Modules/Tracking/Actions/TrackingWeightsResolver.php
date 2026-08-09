<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Actions;

use App\Modules\Institution\Models\Cycle;
use App\Modules\Institution\Models\InstitutionSetting;

/**
 * Cadena de resolución de 3 niveles (decisión confirmada del Hito 4):
 * override del ciclo -> override global -> config('tracking.progress_weights').
 * Reutiliza InstitutionSetting::get() tal cual (mismo caché por
 * institución+key que ya usa el año lectivo) -- el valor se guarda como
 * JSON string dentro de la columna 'value' (string) de institution_settings.
 *
 * @phpstan-type Weights array{evidencias: int, foro: int, chat: int}
 */
class TrackingWeightsResolver
{
    public const GLOBAL_KEY = 'tracking_progress_weights:global';

    /**
     * @return array{evidencias: int, foro: int, chat: int}
     */
    public function forCycle(Cycle $cycle): array
    {
        $cycleOverride = InstitutionSetting::get(self::cycleKey($cycle->id));

        if ($cycleOverride !== null) {
            return json_decode($cycleOverride, true);
        }

        $globalOverride = InstitutionSetting::get(self::GLOBAL_KEY);

        if ($globalOverride !== null) {
            return json_decode($globalOverride, true);
        }

        return config('tracking.progress_weights');
    }

    public static function cycleKey(int $cycleId): string
    {
        return "tracking_progress_weights:cycle:{$cycleId}";
    }
}
