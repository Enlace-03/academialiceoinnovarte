<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Hito 2: corrección del scaffolding original de Assessment. evaluations
// tenía unique(submission_id) — una sola evaluación por entrega, sin espacio
// para auto/coevaluación futura (aunque no se construya todavía). Se
// reemplaza por unique(submission_id, evaluator_type): hoy solo existe el
// tipo 'teacher' (ver config('assessment.evaluator_types')), pero el esquema
// ya admite 'self'/'peer' coexistiendo sin volver a migrar.
return new class extends Migration
{
    public function up(): void
    {
        // MySQL/MariaDB no permite soltar un índice único que sostiene una FK
        // sin soltar primero la FK misma (mismo caso que 000140_change_groups...).
        Schema::table('evaluations', function (Blueprint $table) {
            $table->dropForeign(['submission_id']);
        });

        Schema::table('evaluations', function (Blueprint $table) {
            $table->dropUnique(['submission_id']);
            $table->string('evaluator_type', 20)->default('teacher')->after('evaluated_by');
            $table->unique(['submission_id', 'evaluator_type']);
            $table->softDeletes();
        });

        Schema::table('evaluations', function (Blueprint $table) {
            $table->foreign('submission_id')->references('id')->on('submissions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('evaluations', function (Blueprint $table) {
            $table->dropForeign(['submission_id']);
        });

        Schema::table('evaluations', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropUnique(['submission_id', 'evaluator_type']);
            $table->dropColumn('evaluator_type');
            $table->unique('submission_id');
        });

        Schema::table('evaluations', function (Blueprint $table) {
            $table->foreign('submission_id')->references('id')->on('submissions')->cascadeOnDelete();
        });
    }
};
