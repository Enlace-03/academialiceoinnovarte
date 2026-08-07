<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Hito 1C: equipos de trabajo dentro de un proyecto (viven en un grupo/salón
// concreto), y el cronograma real por fase de cada estudiante.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        // Catálogo de roles de equipo en config('project.team_roles'), no ENUM
        // de base de datos, para poder editarlo sin migración.
        Schema::create('project_team_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role_in_team', 30)->nullable();
            $table->timestamps();
            $table->unique(['project_team_id', 'user_id']);
        });

        Schema::create('student_phase_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('phase_id')->constrained()->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedInteger('extension_count')->default(0);
            $table->timestamps();
            $table->unique(['student_id', 'phase_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_phase_schedules');
        Schema::dropIfExists('project_team_user');
        Schema::dropIfExists('project_teams');
    }
};
