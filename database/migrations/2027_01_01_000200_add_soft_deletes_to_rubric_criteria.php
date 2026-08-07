<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Hito 2: normalización de soft deletes — rubric_criteria era la única tabla
// del módulo Assessment sin softDeletes, inconsistente con rubrics/submissions/
// observations.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rubric_criteria', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('rubric_criteria', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
