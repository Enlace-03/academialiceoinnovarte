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
        // 2. Grados escolares (1–9)
        // ------------------------------------------------------------------
        $grades = [
            1 => '1° de Primaria',
            2 => '2° de Primaria',
            3 => '3° de Primaria',
            4 => '4° de Primaria',
            5 => '5° de Primaria',
            6 => '6°',
            7 => '7°',
            8 => '8°',
            9 => '9°',
        ];

        $gradeIds = [];

        foreach ($grades as $level => $name) {
            $grade = DB::table('school_grades')
                ->where('institution_id', $institutionId)
                ->where('level', $level)
                ->first();

            if (! $grade) {
                $gradeIds[$level] = DB::table('school_grades')->insertGetId([
                    'institution_id' => $institutionId,
                    'name'           => $name,
                    'level'          => $level,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
            } else {
                $gradeIds[$level] = $grade->id;
            }
        }

        // ------------------------------------------------------------------
        // 3. Grupos A y B por grado, año lectivo 2027
        // ------------------------------------------------------------------
        foreach ($gradeIds as $level => $gradeId) {
            foreach (['A', 'B'] as $groupName) {
                $exists = DB::table('groups')
                    ->where('school_grade_id', $gradeId)
                    ->where('name', $groupName)
                    ->where('year', 2027)
                    ->exists();

                if (! $exists) {
                    DB::table('groups')->insert([
                        'uuid'            => Str::uuid(),
                        'school_grade_id' => $gradeId,
                        'name'            => $groupName,
                        'year'            => 2027,
                        'created_at'      => now(),
                        'updated_at'      => now(),
                    ]);
                }
            }
        }

        // ------------------------------------------------------------------
        // 4. Materias
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
    }
}
