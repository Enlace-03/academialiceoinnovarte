<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Módulo Assessment: entregas + evaluación cualitativa por rúbrica de 4 niveles.
// Niveles: not_achieved(1) | partially_achieved(2) | achieved(3) | exceeded(4).
// El valor numérico es interno para cálculos; la UI SIEMPRE muestra el texto en español.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rubrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('rubric_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rubric_id')->constrained()->cascadeOnDelete();
            $table->string('name');                 // ej. "Comprensión del tema"
            $table->json('level_descriptions');     // {"not_achieved": "...", "partially_achieved": "...", ...}
            $table->unsignedTinyInteger('position');
            $table->timestamps();
        });

        // FK diferida desde expected_evidences hacia rubrics.
        Schema::table('expected_evidences', function (Blueprint $table) {
            $table->foreign('rubric_id')->references('id')->on('rubrics')->nullOnDelete();
        });

        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('expected_evidence_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->longText('text_content')->nullable(); // para evidencias tipo texto
            $table->string('status')->default('submitted'); // submitted | evaluated | returned
            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['expected_evidence_id', 'student_id']);
        });

        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('submission_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('evaluated_by')->constrained('users')->cascadeOnDelete();
            $table->text('feedback')->nullable(); // comentario general del docente
            $table->timestamp('evaluated_at');
            $table->timestamps();
        });

        Schema::create('evaluation_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rubric_criterion_id')->constrained('rubric_criteria')->cascadeOnDelete();
            $table->enum('level', ['not_achieved', 'partially_achieved', 'achieved', 'exceeded']);
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->unique(['evaluation_id', 'rubric_criterion_id']);
        });

        // Observaciones cualitativas libres del docente (estilo Qino/Fontán).
        Schema::create('observations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->text('content');
            $table->boolean('visible_to_parents')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('observations');
        Schema::dropIfExists('evaluation_results');
        Schema::dropIfExists('evaluations');
        Schema::dropIfExists('submissions');
        Schema::table('expected_evidences', function (Blueprint $table) {
            $table->dropForeign(['rubric_id']);
        });
        Schema::dropIfExists('rubric_criteria');
        Schema::dropIfExists('rubrics');
    }
};
