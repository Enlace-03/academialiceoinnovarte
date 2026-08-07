<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Hito 2: RubricForm usa Repeater::reorderable('position'), que solo escribe
// la columna cuando el usuario reordena por drag-and-drop, no al crear. Sin
// default, el primer guardado de un criterio nuevo fallaba por NOT NULL.
// SQL crudo porque doctrine/dbal (requerido por Blueprint::change()) no está
// instalado en este proyecto.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE rubric_criteria MODIFY position TINYINT UNSIGNED NOT NULL DEFAULT 0');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE rubric_criteria MODIFY position TINYINT UNSIGNED NOT NULL');
    }
};
