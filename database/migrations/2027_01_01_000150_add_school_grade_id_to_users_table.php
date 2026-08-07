<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Hito 1B: el grado del estudiante pasa a ser un dato propio del estudiante,
// independiente del grupo (el grupo ahora es por ciclo, no por grado).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('school_grade_id')->nullable()->after('group_id')
                ->constrained('school_grades')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('school_grade_id');
        });
    }
};
