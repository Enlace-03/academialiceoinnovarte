<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Módulo Project: el corazón del ABP.
// Proyecto → Fases/Hitos → Guías (pre-creadas) + Recursos (del docente) + Evidencias esperadas.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('duration_months'); // 3 o 6
            $table->date('starts_at');
            $table->date('ends_at');
            $table->string('status')->default('draft'); // draft | active | finished | archived
            $table->json('progress_weights')->nullable(); // override de pesos de la barra de avance
            $table->timestamps();
            $table->softDeletes();
        });

        // Un proyecto ABP es transversal: puede cubrir varias materias.
        Schema::create('project_subject', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->unique(['project_id', 'subject_id']);
        });

        Schema::create('phases', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('position'); // orden
            $table->date('suggested_deadline')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['project_id', 'position']);
        });

        // Las guías "ya están creadas": contenido base del colegio.
        Schema::create('guides', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('phase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->longText('content')->nullable(); // HTML/Markdown de la guía
            $table->unsignedTinyInteger('position');
            $table->boolean('is_published')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        // Recursos complementarios que sube el docente (archivos vía medialibrary, o enlaces).
        Schema::create('resources', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('guide_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('phase_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('type');          // file | link | video
            $table->string('url')->nullable(); // para links/videos; archivos van por medialibrary
            $table->timestamps();
            $table->softDeletes();
        });

        // Qué debe entregar el estudiante en cada fase.
        Schema::create('expected_evidences', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('phase_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('instructions')->nullable();
            $table->string('type');           // file | text | forum_participation
            $table->foreignId('rubric_id')->nullable(); // FK se agrega en la migración de assessment
            $table->date('deadline')->nullable();
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
        Schema::dropIfExists('project_subject');
        Schema::dropIfExists('projects');
    }
};
