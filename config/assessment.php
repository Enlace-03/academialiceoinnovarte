<?php

// config/assessment.php
//
// Catálogo de tipos de evaluador. Hoy solo 'teacher' está en uso; 'self' y
// 'peer' quedan reservados para cuando se active auto/coevaluación (sin
// resolver todavía). evaluations.unique(submission_id, evaluator_type) ya
// admite que los tres coexistan sin volver a migrar.

return [

    'evaluator_types' => [
        'teacher' => 'Docente',
        'self' => 'Autoevaluación',
        'peer' => 'Coevaluación',
    ],

];
