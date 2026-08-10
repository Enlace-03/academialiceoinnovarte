<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InstitutionSeeder extends Seeder
{
    public function run(): void
    {
        // ------------------------------------------------------------------
        // 1. Institución
        // ------------------------------------------------------------------
        $institution = DB::table('institutions')->where('name', 'Liceo Innovarte')->first();

        if (! $institution) {
            $institutionId = DB::table('institutions')->insertGetId([
                'uuid'       => Str::uuid(),
                'name'       => 'Liceo Innovarte',
                'city'       => 'Pereira, Colombia',
                'settings'   => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $institutionId = $institution->id;
        }

        // ------------------------------------------------------------------
        // 2. Ciclos de desarrollo del pensamiento (1–4)
        // ------------------------------------------------------------------
        // Nombres y descripciones: placeholders pendientes de confirmación
        // institucional (roadmap Hito 1A). Fáciles de renombrar aquí, nunca
        // hardcodeados en lógica de negocio.
        $cycles = [
            1 => ['name' => 'Exploratorio', 'description' => 'Desarrollo de la curiosidad, la exploración sensorial y el reconocimiento del entorno inmediato.'],
            2 => ['name' => 'Conceptual', 'description' => 'Construcción de conceptos y relaciones a partir de la exploración estructurada.'],
            3 => ['name' => 'Contextual', 'description' => 'Aplicación de conceptos en contextos reales, con mayor autonomía frente al proyecto.'],
            4 => ['name' => 'Proyectivo', 'description' => 'Diseño y ejecución de proyectos propios, con proyección a la vida y al entorno social.'],
        ];

        $cycleIds = [];

        foreach ($cycles as $order => $cycle) {
            $existing = DB::table('cycles')
                ->where('institution_id', $institutionId)
                ->where('order', $order)
                ->first();

            if (! $existing) {
                $cycleIds[$order] = DB::table('cycles')->insertGetId([
                    'institution_id' => $institutionId,
                    'name'           => $cycle['name'],
                    'order'          => $order,
                    'description'    => $cycle['description'],
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
            } else {
                $cycleIds[$order] = $existing->id;
            }
        }

        // ------------------------------------------------------------------
        // 3. Campos de pensamiento (1–4)
        // ------------------------------------------------------------------
        // Nombres: placeholders pendientes de confirmación institucional,
        // los 4 nombres que pidió el roadmap del Hito 1A.
        $thinkingFields = [
            1 => ['name' => 'Lenguaje y Comunicación', 'purpose' => 'Desarrollar la comprensión y expresión oral, escrita y simbólica.'],
            2 => ['name' => 'Pensamiento Matemático', 'purpose' => 'Desarrollar el razonamiento lógico-matemático y la resolución de problemas.'],
            3 => ['name' => 'Exploración y Comprensión del Mundo Natural y Social', 'purpose' => 'Desarrollar la comprensión del entorno natural, social y cultural.'],
            4 => ['name' => 'Desarrollo Personal y Social', 'purpose' => 'Desarrollar la identidad, la autonomía y la convivencia.'],
        ];

        foreach ($thinkingFields as $order => $field) {
            $exists = DB::table('thinking_fields')
                ->where('institution_id', $institutionId)
                ->where('order', $order)
                ->exists();

            if (! $exists) {
                DB::table('thinking_fields')->insert([
                    'institution_id' => $institutionId,
                    'name'           => $field['name'],
                    'slug'           => Str::slug($field['name']),
                    'purpose'        => $field['purpose'],
                    'order'          => $order,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
            }
        }

        // ------------------------------------------------------------------
        // 4. Grados escolares (0–9), cada uno asignado a su ciclo
        // ------------------------------------------------------------------
        $grades = [
            0 => ['name' => 'Transición', 'cycle' => 1],
            1 => ['name' => '1° de Primaria', 'cycle' => 1],
            2 => ['name' => '2° de Primaria', 'cycle' => 1],
            3 => ['name' => '3° de Primaria', 'cycle' => 2],
            4 => ['name' => '4° de Primaria', 'cycle' => 2],
            5 => ['name' => '5° de Primaria', 'cycle' => 2],
            6 => ['name' => '6°', 'cycle' => 3],
            7 => ['name' => '7°', 'cycle' => 3],
            8 => ['name' => '8°', 'cycle' => 4],
            9 => ['name' => '9°', 'cycle' => 4],
        ];

        $gradeIds = [];

        foreach ($grades as $level => $grade) {
            $cycleId = $cycleIds[$grade['cycle']];

            $existing = DB::table('school_grades')
                ->where('institution_id', $institutionId)
                ->where('level', $level)
                ->first();

            if (! $existing) {
                $gradeIds[$level] = DB::table('school_grades')->insertGetId([
                    'institution_id' => $institutionId,
                    'cycle_id'       => $cycleId,
                    'name'           => $grade['name'],
                    'level'          => $level,
                    'is_active'      => true,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
            } else {
                $gradeIds[$level] = $existing->id;

                if ($existing->cycle_id === null) {
                    DB::table('school_grades')
                        ->where('id', $existing->id)
                        ->update(['cycle_id' => $cycleId, 'updated_at' => now()]);
                }
            }
        }

        // ------------------------------------------------------------------
        // 5. Grupos por ciclo (Hito 1B: el grupo/salón ya no es dueño de un
        //    grado individual, sino de un ciclo — puede mezclar estudiantes
        //    de varios grados del mismo ciclo). Confirmado con Diego (product
        //    owner) el 2026-08-09: un solo grupo real por ciclo para el año
        //    lectivo vigente; la estructura sigue soportando más de uno si
        //    el crecimiento de matrícula lo requiere.
        // ------------------------------------------------------------------
        foreach ($cycleIds as $order => $cycleId) {
            $groupName = $cycles[$order]['name'];

            $exists = DB::table('groups')
                ->where('cycle_id', $cycleId)
                ->where('name', $groupName)
                ->where('year', 2027)
                ->exists();

            if (! $exists) {
                DB::table('groups')->insert([
                    'uuid'       => Str::uuid(),
                    'cycle_id'   => $cycleId,
                    'name'       => $groupName,
                    'year'       => 2027,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // ------------------------------------------------------------------
        // 6. Materias
        // ------------------------------------------------------------------
        $subjects = [
            // Tradicionales
            ['name' => 'Matemáticas',              'is_innovative' => false],
            ['name' => 'Lengua Castellana',         'is_innovative' => false],
            ['name' => 'Ciencias Naturales',        'is_innovative' => false],
            ['name' => 'Ciencias Sociales',         'is_innovative' => false],
            ['name' => 'Inglés',                    'is_innovative' => false],
            ['name' => 'Tecnología e Informática',  'is_innovative' => false],
            ['name' => 'Ética y Valores',           'is_innovative' => false],
            ['name' => 'Educación Artística',       'is_innovative' => false],
            ['name' => 'Educación Física',          'is_innovative' => false],
            // Innovadoras
            ['name' => 'Pensamiento Crítico',       'is_innovative' => true],
            ['name' => 'Inteligencia Emocional',    'is_innovative' => true],
            ['name' => 'Liderazgo',                 'is_innovative' => true],
            ['name' => 'Programación',              'is_innovative' => true],
            ['name' => 'Habilidades Comunicativas', 'is_innovative' => true],
            ['name' => 'Creatividad',               'is_innovative' => true],
            ['name' => 'Proyecto de Vida',          'is_innovative' => true],
            ['name' => 'Educación Financiera',      'is_innovative' => true],
        ];

        foreach ($subjects as $subject) {
            $exists = DB::table('subjects')
                ->where('institution_id', $institutionId)
                ->where('name', $subject['name'])
                ->exists();

            if (! $exists) {
                DB::table('subjects')->insert([
                    'institution_id' => $institutionId,
                    'name'           => $subject['name'],
                    'is_innovative'  => $subject['is_innovative'],
                    'color'          => null,
                    'is_active'      => true,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
            }
        }

        // ------------------------------------------------------------------
        // 7. Año lectivo vigente
        // ------------------------------------------------------------------
        // Fuente de verdad real (no config/school.php): hoy solo hay grupos
        // sembrados para 2027, así que ese es el año lectivo vigente. Editable
        // luego desde /admin en "Configuración institucional".
        $existingSetting = DB::table('institution_settings')
            ->where('institution_id', $institutionId)
            ->where('key', 'current_academic_year')
            ->exists();

        if (! $existingSetting) {
            DB::table('institution_settings')->insert([
                'institution_id' => $institutionId,
                'key'            => 'current_academic_year',
                'value'          => '2027',
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        }
    }
}
