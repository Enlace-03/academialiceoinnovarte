<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Hito 1B: un grupo/salón ya no pertenece a un grado individual — pertenece a
// un ciclo, y puede mezclar estudiantes de varios grados del mismo ciclo.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->foreignId('cycle_id')->nullable()->after('uuid')
                ->constrained()->cascadeOnDelete();
        });

        // Backfill desde el grado actual antes de soltar school_grade_id.
        DB::table('groups')
            ->join('school_grades', 'groups.school_grade_id', '=', 'school_grades.id')
            ->update(['groups.cycle_id' => DB::raw('school_grades.cycle_id')]);

        // Varios grados pueden compartir ciclo, así que grupos "A"/"B" de
        // grados distintos ahora colisionan en (cycle_id, name, year).
        // Antes de soltar school_grade_id (y con él, la única forma de saber
        // de qué grado venía cada grupo), desambiguar añadiendo el nombre
        // del grado original a cualquier grupo que quede duplicado.
        $duplicates = DB::table('groups')
            ->select('cycle_id', 'name', 'year')
            ->groupBy('cycle_id', 'name', 'year')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            $rows = DB::table('groups')
                ->join('school_grades', 'groups.school_grade_id', '=', 'school_grades.id')
                ->where('groups.cycle_id', $duplicate->cycle_id)
                ->where('groups.name', $duplicate->name)
                ->where('groups.year', $duplicate->year)
                ->select('groups.id', 'school_grades.name as grade_name')
                ->get();

            foreach ($rows as $row) {
                DB::table('groups')->where('id', $row->id)->update([
                    'name' => "{$duplicate->name} ({$row->grade_name})",
                ]);
            }
        }

        // MySQL usa el índice único (school_grade_id, name, year) como soporte
        // de la FK de school_grade_id — hay que soltar primero la constraint,
        // en su propio ALTER TABLE, antes de poder soltar ese índice.
        Schema::table('groups', function (Blueprint $table) {
            $table->dropForeign(['school_grade_id']);
        });

        Schema::table('groups', function (Blueprint $table) {
            $table->dropUnique('groups_school_grade_id_name_year_unique');
            $table->dropColumn('school_grade_id');
        });

        Schema::table('groups', function (Blueprint $table) {
            $table->unsignedBigInteger('cycle_id')->nullable(false)->change();
            $table->unique(['cycle_id', 'name', 'year']);
        });
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropForeign(['cycle_id']);
        });

        Schema::table('groups', function (Blueprint $table) {
            $table->dropUnique('groups_cycle_id_name_year_unique');
            $table->dropColumn('cycle_id');
        });

        Schema::table('groups', function (Blueprint $table) {
            $table->foreignId('school_grade_id')->nullable()->after('uuid')
                ->constrained()->cascadeOnDelete();
        });

        Schema::table('groups', function (Blueprint $table) {
            $table->unique(['school_grade_id', 'name', 'year']);
        });
    }
};
