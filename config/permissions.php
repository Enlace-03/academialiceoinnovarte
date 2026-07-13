<?php

// config/permissions.php
//
// Fuente única de verdad del catálogo de permisos del sistema.
// Los permisos son atómicos y marcables individualmente (spatie/laravel-permission).
// Los "presets" son plantillas opcionales para acelerar la creación de personal;
// NO son roles rígidos: al crear un usuario se parte de un preset y se ajustan las casillas.
//
// Estudiantes y padres son la excepción: usan roles fijos uniformes (student, parent),
// nunca se configuran permiso por permiso.

return [

    // ---------------------------------------------------------------------
    // Catálogo de permisos, agrupados por área para la UI de casillas.
    // La clave del grupo es solo para presentación (títulos de sección en Filament).
    // ---------------------------------------------------------------------
    'catalog' => [

        'Gestión de usuarios' => [
            'users.view'             => 'Ver usuarios',
            'users.create'           => 'Crear usuarios',
            'users.update'           => 'Editar usuarios',
            'users.delete'           => 'Eliminar usuarios',
            'users.grant'            => 'Otorgar permisos a otros usuarios (delegar)',
        ],

        'Estudiantes' => [
            'students.create'        => 'Crear estudiantes (matricular)',
            'students.create.scoped' => 'Crear estudiantes con alcance limitado (concedido por rector)',
            'students.view'          => 'Ver estudiantes',
            'students.update'        => 'Editar datos de estudiantes',
        ],

        'Institución' => [
            'institution.manage'         => 'Configuración general de la institución y año lectivo',
            'school-grades.manage'       => 'Crear y editar grados escolares',
            'groups.manage'              => 'Crear y editar grupos',
            'subjects.manage'            => 'Crear y editar materias',
            'subjects.view'              => 'Ver listado de materias',
            'teacher-assignments.manage' => 'Asignar profesores a materias y grupos',
        ],

        'Proyectos ABP' => [
            'projects.view.own'   => 'Ver proyectos propios',
            'projects.view.all'   => 'Ver todos los proyectos',
            'projects.create'     => 'Crear proyectos',
            'projects.update.own' => 'Editar proyectos propios',
            'projects.update.all' => 'Editar cualquier proyecto',
            'phases.manage'       => 'Gestionar fases y guías',
            'resources.manage'    => 'Subir recursos complementarios',
        ],

        'Evaluación' => [
            'submissions.evaluate'    => 'Evaluar entregas con rúbrica',
            'observations.write'      => 'Escribir observaciones',
            'observations.view.all'   => 'Ver observaciones de todos los estudiantes',
        ],

        'Seguimiento y analítica' => [
            'dashboard.institutional.view' => 'Ver dashboard institucional',
            'students-at-risk.view.all'    => 'Ver estudiantes en riesgo de toda la institución',
            'reports.export'               => 'Exportar reportes',
        ],

        'Comunidad' => [
            'forums.moderate' => 'Moderar foros y chat',
        ],

        'Sistema' => [
            'settings.manage'       => 'Gestionar configuración del sistema',
            'activity-logs.view'    => 'Ver registros de auditoría',
            'avatar-messages.manage'=> 'Gestionar mensajes de los avatares',
        ],
    ],

    // ---------------------------------------------------------------------
    // Presets: plantillas de creación rápida de personal.
    // Al crear un usuario en Filament, elegir un preset precarga estas casillas;
    // luego se pueden ajustar (siempre dentro del techo del otorgante).
    // ---------------------------------------------------------------------
    'presets' => [

        'super_admin' => '*', // todos los permisos del catálogo

        'rector' => [
            'users.view', 'users.create', 'users.update', 'users.grant',
            'students.create', 'students.view', 'students.update',
            'institution.manage',
            'school-grades.manage', 'groups.manage', 'subjects.manage', 'subjects.view',
            'teacher-assignments.manage',
            'projects.view.all', 'projects.create', 'projects.update.all',
            'phases.manage', 'resources.manage',
            'submissions.evaluate', 'observations.write', 'observations.view.all',
            'dashboard.institutional.view', 'students-at-risk.view.all', 'reports.export',
            'forums.moderate',
            'avatar-messages.manage',
        ],

        'coordinator' => [
            'users.view', 'users.create',
            'students.view',
            'groups.manage', 'subjects.view',
            'projects.view.all',
            'observations.view.all',
            'dashboard.institutional.view', 'students-at-risk.view.all',
        ],

        'secretary' => [
            'users.view', 'users.create',
            'students.create', 'students.view', 'students.update',
        ],

        'teacher' => [
            'students.view',
            'subjects.view',
            'projects.view.own', 'projects.create', 'projects.update.own',
            'phases.manage', 'resources.manage',
            'submissions.evaluate', 'observations.write',
            'forums.moderate',
        ],
    ],

    // ---------------------------------------------------------------------
    // Roles fijos uniformes (no se configuran por permiso).
    // ---------------------------------------------------------------------
    'fixed_roles' => [
        'student' => [
            // Los estudiantes no usan permisos del catálogo; su acceso se controla
            // por middleware de ruta (role:student) y por scoping a auth()->id().
        ],
        'parent' => [
            // Igual que student: acceso vía middleware (role:parent) y relación parent_student.
        ],
        
    ],
    'admin_panel_permission_prefixes' => ['users.', 'institution.'],
];
