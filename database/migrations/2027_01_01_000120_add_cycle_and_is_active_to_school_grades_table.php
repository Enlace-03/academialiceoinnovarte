<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// cycle_id nullable: los grados existentes no tienen ciclo asignado todavía y no
// deben romperse; el seeder/UI los asigna después.
// is_active es una bandera administrativa explícita (no derivada de matrícula
// vigente): rectora/coordinador la usan para sacar un grado de los selectores
// de nueva matrícula sin afectar a estudiantes ya matriculados en él.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_grades', function (Blueprint $table) {
            $table->foreignId('cycle_id')->nullable()->after('institution_id')
                ->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('level');
        });
    }

    public function down(): void
    {
        Schema::table('school_grades', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cycle_id');
            $table->dropColumn('is_active');
        });
    }
};
