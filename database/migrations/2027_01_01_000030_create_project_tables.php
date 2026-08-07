<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Módulo Project: el corazón del ABP.
// Proyecto (por ciclo, 1 de 2 al año) → Fases (las 4 institucionales, fijas y en
// orden) → Guías (pre-creadas) + Recursos (del docente) + Evidencias esperadas.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('cycle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->longText('problem_situation')->nullable();
            $table->text('guiding_question')->nullable();
            $table->text('purpose')->nullable();
            $table->unsignedTinyInteger('semester'); // 1 o 2
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('suggested_duration_weeks')->nullable();
            $table->longText('expected_impact')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Un proyecto ABP puede integrar varios campos de pensamiento.
        Schema::create('project_thinking_field', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('thinking_field_id')->constrained()->cascadeOnDelete();
            $table->unique(['project_id', 'thinking_field_id']);
        });

        Schema::create('phases', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedTinyInteger('order'); // 1..4, orden institucional fijo
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['project_id', 'order']);
        });

        // Las guías "ya están creadas": contenido base del colegio.
        Schema::create('guides', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('phase_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->longText('content')->nullable(); // HTML/Markdown de la guía
            $table->timestamps();
            $table->softDeletes();
        });

        // Recursos complementarios que sube el docente: cuelgan de una fase, o
        // específicamente de una guía dentro de esa fase.
        Schema::create('resources', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('phase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guide_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('type', 20); // pdf | video | enlace
            $table->string('url_or_path');
            $table->timestamps();
            $table->softDeletes();
        });

        // Qué debe entregar el estudiante en cada fase. Las evidencias que
        // comparten el mismo alternative_group (no nulo) son mutuamente
        // excluyentes: el estudiante/equipo entrega una de ellas, no todas.
        Schema::create('expected_evidences', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('phase_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30); // archivo | texto | participación en foro
            $table->text('description')->nullable();
            $table->boolean('is_required')->default(true);
            $table->string('alternative_group', 60)->nullable();
            $table->foreignId('rubric_id')->nullable(); // FK diferida, se agrega en la migración de assessment (Hito 2)
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expected_evidences');
        Schema::dropIfExists('resources');
        Schema::dropIfExists('guides');
        Schema::dropIfExists('phases');
        Schema::dropIfExists('project_thinking_field');
        Schema::dropIfExists('projects');
    }
};
