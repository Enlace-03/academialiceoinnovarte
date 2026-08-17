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
            'students.photo.moderate' => 'Eliminar o bloquear la foto de perfil de estudiantes',
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
            'tracking.settings.manage'     => 'Configurar los pesos de la fórmula de avance por ciclo',
        ],

        'Comunidad' => [
            'chat.moderate'       => 'Ocultar mensajes de chat de cualquier grupo',
            'gallery.publish'     => 'Publicar en la galería de fotos (institucional o de proyecto)',
            'gallery.update.own'  => 'Editar publicaciones propias de la galería',
            'gallery.update.all'  => 'Editar cualquier publicación de la galería',

            // private_chats.view.all es deliberadamente de SOLO LECTURA -- nunca
            // implica autoridad para escribir en un chat privado ajeno. Decisión de
            // gobernanza, no un descuido: la Ley 1620 de 2013 (protección escolar
            // contra la violencia, deber institucional de vigilar el trato de
            // personal hacia estudiantes) exige que coordinación/rectoría puedan
            // SUPERVISAR cualquier conversación estudiante-docente; el principio de
            // minimización de datos de la Ley 1581 de 2012 (mismo criterio ya
            // aplicado en RecordDataTreatmentConsentAction) exige que esa vigilancia
            // no se convierta en autoridad operativa de facto. Escribir en un chat
            // puntual sigue exigiendo autoridad REAL sobre ese proyecto
            // (projects.update.all, o ser el docente responsable directo vía
            // projects.update.own), nunca el solo hecho de poder leerlo -- ver
            // PrivateChatThreadPolicy (view()/viewContext() vs. create()), probado en
            // PrivateChatThreadPolicyTest::test_institutional_viewer_with_only_view_all_can_read_but_not_write.
            // NO fusionar private_chats.view.all con ninguna autoridad de escritura
            // en un preset futuro sin revisar esta nota primero.
            'private_chats.view.all' => 'Ver cualquier chat privado estudiante-docente (visibilidad institucional)',
            'private_chats.moderate' => 'Ocultar mensajes de chat privado de cualquier proyecto',
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
            'students.create', 'students.view', 'students.update', 'students.photo.moderate',
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
            'tracking.settings.manage',
            'avatar-messages.manage',
            'chat.moderate',
            'gallery.publish', 'gallery.update.all',
            // Lectura institucional + moderación (protección infantil, Ley 1620) --
            // la escritura real en un chat puntual ya viene dada arriba por
            // projects.update.all, NO por estos dos permisos. Ver la nota junto a
            // private_chats.view.all en 'catalog' antes de tocar esta línea.
            'private_chats.view.all', 'private_chats.moderate',
        ],

        'coordinator' => [
            'users.view', 'users.create',
            'students.view', 'students.photo.moderate',
            'institution.groups.manage', 'subjects.view',
            'projects.view.all', 'projects.create', 'projects.update.all',
            'phases.manage', 'resources.manage',
            'rubrics.manage',
            'observations.view.all',
            'dashboard.institutional.view', 'students-at-risk.view.all',
            // Mismo alcance que rector ya tiene sobre esta pantalla (Hito de
            // permisos, corrección #1) -- coordinator gestiona seguimiento
            // institucional tanto como rector, no hay razón de negocio para
            // que solo rector pueda ajustar los pesos de avance. Sin cambio
            // de comportamiento para rector.
            'tracking.settings.manage',
            'chat.moderate',
            'gallery.publish', 'gallery.update.all',
            // Lectura institucional + moderación (protección infantil, Ley 1620) --
            // la escritura real en un chat puntual ya viene dada arriba por
            // projects.update.all, NO por estos dos permisos. Ver la nota junto a
            // private_chats.view.all en 'catalog' antes de tocar esta línea.
            'private_chats.view.all', 'private_chats.moderate',
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
            'gallery.publish', 'gallery.update.own',
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
