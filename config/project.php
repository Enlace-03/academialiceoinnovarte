<?php

// config/project.php
//
// Catálogo de roles de equipo dentro de un ProjectTeam. No es un ENUM de base
// de datos (mismo patrón que config/permissions.php): así se puede editar o
// ampliar sin necesidad de migración si el colegio agrega o cambia roles.

return [

    'team_roles' => [
        'investigador' => 'Investigador',
        'registrador' => 'Registrador',
        'creativo' => 'Creativo',
        'vocero' => 'Vocero',
    ],

];
