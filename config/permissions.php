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
            'institution.manage'                => 'Configuración general de la institución y año lectivo',
            'institution.settings.manage'        => 'Editar configuración institucional (año lectivo vigente y otros parámetros)',
            'institution.cycles.manage'          => 'Crear y editar ciclos de desarrollo del pensamiento',
            'institution.thinking-fields.manage' => 'Crear y editar campos de pensamiento',
            'institution.school-grades.manage'   => 'Crear y editar grados escolares',
            'institution.groups.manage'          => 'Crear y editar grupos',
            'subjects.manage'                    => 'Crear y editar materias',
            'subjects.view'                      => 'Ver listado de materias',
            'teacher-assignments.manage'         => 'Asignar profesores a materias y grupos',
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
            'rubrics.manage'          => 'Gestionar el banco de rúbricas',
            'submissions.evaluate'    => 'Evaluar entregas con rúbrica',
            'observations.write.own'  => 'Escribir observaciones propias',
            'observations.write.all'  => 'Escribir observaciones de cualquier estudiante',
            'observations.view.all'   => 'Ver observaciones de todos los estudiantes',
        ],

        'Seguimiento y analítica' => [
            'dashboard.institutional.view' => 'Ver dashboard institucional',
            'students-at-risk.view.all'    => 'Ver estudiantes en riesgo de toda la institución',
            'reports.export'               => 'Exportar reportes',
        ],

        'Comunidad' => [
            'chat.moderate' => 'Ocultar mensajes de chat de cualquier grupo',
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
            'institution.settings.manage',
            'institution.cycles.manage', 'institution.thinking-fields.manage',
            'institution.school-grades.manage', 'institution.groups.manage',
            'subjects.manage', 'subjects.view',
            'teacher-assignments.manage',
            'projects.view.all', 'projects.create', 'projects.update.all',
            'phases.manage', 'resources.manage',
            'rubrics.manage', 'submissions.evaluate',
            'observations.write.own', 'observations.write.all', 'observations.view.all',
            'dashboard.institutional.view', 'students-at-risk.view.all', 'reports.export',
            'avatar-messages.manage',
            'chat.moderate',
        ],

        'coordinator' => [
            'users.view', 'users.create',
            'students.view',
            'institution.groups.manage', 'subjects.view',
            'projects.view.all', 'projects.create', 'projects.update.all',
            'phases.manage', 'resources.manage',
            'rubrics.manage',
            'observations.view.all',
            'dashboard.institutional.view', 'students-at-risk.view.all',
            'chat.moderate',
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
            'rubrics.manage', 'submissions.evaluate', 'observations.write.own',
        ],
    ],

    // ---------------------------------------------------------------------
    // Categoría de cada rol: determina la regla de exclusividad de roles de
    // identidad (ver App\Rules\ExclusiveIdentityRoleRule). Los roles "staff"
    // se pueden combinar libremente entre sí; los "identity" no se pueden
    // combinar con ningún otro rol (ni staff ni entre sí).
    // ---------------------------------------------------------------------
    'role_categories' => [
        'super_admin' => 'staff',
        'rector' => 'staff',
        'coordinator' => 'staff',
        'secretary' => 'staff',
        'teacher' => 'staff',
        'student' => 'identity',
        'parent' => 'identity',
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
