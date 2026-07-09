<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Módulo Institution: institución, grados escolares, grupos, materias, asignación docente.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institutions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('city')->nullable();
            $table->json('settings')->nullable(); // color coding, pesos de avance por defecto, etc.
            $table->timestamps();
        });

        Schema::create('school_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('name');            // "1° de primaria" ... "9°"
            $table->unsignedTinyInteger('level'); // 1..9 para ordenar y para lógica primaria/secundaria
            $table->timestamps();
            $table->unique(['institution_id', 'level']);
        });

        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('school_grade_id')->constrained()->cascadeOnDelete();
            $table->string('name');            // "A", "B"
            $table->unsignedSmallInteger('year'); // año lectivo, ej. 2027
            $table->timestamps();
            $table->unique(['school_grade_id', 'name', 'year']);
        });

        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_innovative')->default(false); // pensamiento crítico, IE, liderazgo...
            $table->string('color', 7)->nullable();           // hex para UI, según color coding
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('teacher_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['teacher_id', 'subject_id', 'group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_assignments');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('groups');
        Schema::dropIfExists('school_grades');
        Schema::dropIfExists('institutions');
    }
};
