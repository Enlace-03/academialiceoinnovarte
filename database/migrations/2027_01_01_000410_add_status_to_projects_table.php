<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Separación vista/edición de Proyecto + estado borrador/publicado: un
// docente puede armar un proyecto sin que aparezca de inmediato en el
// portal de sus estudiantes -- ProjectPolicy (rama estudiante) exige
// status = published, además de la verificación de ciclo ya existente.
//
// Backfill EN EL MISMO up(), antes de que el default 'draft' de la columna
// aplique solo a filas nuevas: todo proyecto ya existente en la base
// (creado antes de que este concepto existiera) se marca published -- no
// deben desaparecer del portal de sus estudiantes por una migración de
// esquema. Solo los proyectos creados de ahora en adelante nacen en
// borrador.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('status', 20)->default('draft')->after('title');
        });

        DB::table('projects')->update(['status' => 'published']);
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
