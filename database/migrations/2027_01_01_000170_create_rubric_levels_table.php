<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Hito 2: catálogo global de niveles de rúbrica (Inicio → En proceso → Logro
// esperado → Logro destacado). Tabla, no ENUM de BD, para poder agregar un
// quinto nivel editando datos, sin migración. Sembrado por RubricLevelSeeder.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rubric_levels', function (Blueprint $table) {
            $table->id();
            $table->string('key', 30)->unique();
            $table->string('label', 60);
            $table->string('color', 20);
            $table->unsignedTinyInteger('order')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rubric_levels');
    }
};
