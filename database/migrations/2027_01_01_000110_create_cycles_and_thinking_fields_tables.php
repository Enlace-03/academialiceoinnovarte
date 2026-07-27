<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Estructura institucional: ciclos de desarrollo del pensamiento y campos de pensamiento.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60); // "Exploratorio", "Conceptual", "Contextual", "Proyectivo"
            $table->unsignedTinyInteger('order'); // 1..4
            $table->text('description')->nullable(); // habilidad central del ciclo
            $table->timestamps();
            $table->unique(['institution_id', 'order']);
        });

        Schema::create('thinking_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('slug', 100);
            $table->text('purpose')->nullable();
            $table->unsignedTinyInteger('order'); // 1..4
            $table->timestamps();
            $table->unique(['institution_id', 'slug']);
            $table->unique(['institution_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('thinking_fields');
        Schema::dropIfExists('cycles');
    }
};
