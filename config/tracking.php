<?php

// Pesos de fábrica de la fórmula de avance mecánico (Hito 4). Sirven de
// último nivel de la cadena de resolución (ciclo -> global -> este default)
// -- ver App\Modules\Tracking\Actions\TrackingWeightsResolver. Los tres
// valores deben sumar 100, igual que exige la validación del formulario de
// configuración por ciclo.
return [
    'progress_weights' => [
        'evidencias' => 60,
        'foro' => 25,
        'chat' => 15,
    ],
];
