<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Hito 2: corrección del scaffolding original de Assessment (auditada antes de
// construir el módulo). evaluation_results.level era un ENUM de BD con 4
// valores en inglés ya descartados; se reemplaza por una FK real al catálogo
// rubric_levels, consistente con la decisión de niveles-como-tabla.
//
// Verificado con consulta real antes de esta migración: evaluation_results
// tenía 0 filas en liceo_innovarte y liceo_innovarte_testing — es scaffolding
// sin código de aplicación encima, no hay datos que preservar.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation_results', function (Blueprint $table) {
            $table->dropColumn('level');
        });

        Schema::table('evaluation_results', function (Blueprint $table) {
            $table->foreignId('rubric_level_id')
                ->after('rubric_criterion_id')
                ->constrained()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('evaluation_results', function (Blueprint $table) {
            $table->dropForeign(['rubric_level_id']);
            $table->dropColumn('rubric_level_id');
        });

        Schema::table('evaluation_results', function (Blueprint $table) {
            $table->enum('level', ['not_achieved', 'partially_achieved', 'achieved', 'exceeded'])
                ->after('rubric_criterion_id');
        });
    }
};
