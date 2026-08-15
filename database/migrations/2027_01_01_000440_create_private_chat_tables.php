<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chat privado estudiante<->docente, con alcance de PROYECTO (nunca de
 * evidencia puntual) -- dos tipos, 'individual' (estudiante+docente) y
 * 'team' (integrantes de un ProjectTeam+docente), viviendo en la misma
 * tabla de hilos porque comparten exactamente la misma forma de mensaje
 * (private_chat_messages), solo cambia quién más participa.
 *
 * student_id/team_id son mutuamente excluyentes según 'type' (uno de los
 * dos siempre NULL) -- reforzado a nivel de aplicación (Action de envío),
 * no con un CHECK constraint (MySQL 8/MariaDB los soporta pero el resto
 * del proyecto no los usa en ningún lado).
 *
 * unique(project_id, type, student_id) / unique(project_id, type, team_id):
 * MySQL/MariaDB (InnoDB) NO compara NULLs entre sí para unicidad -- varias
 * filas 'team' (student_id siempre NULL) conviven sin problema bajo el
 * primer índice, y varias filas 'individual' (team_id siempre NULL) bajo
 * el segundo. No hace falta ningún índice único parcial explícito ni un
 * CHECK: el comportamiento estándar de NULL en índices únicos ya da
 * exactamente la semántica pedida (como máximo un hilo individual por
 * project_id+student_id, como máximo un hilo de equipo por
 * project_id+team_id) sin permitir filas 'individual' duplicadas ni
 * filas 'team' duplicadas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('private_chat_threads', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // 'individual' | 'team'
            $table->foreignId('student_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->constrained('project_teams')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'type', 'student_id']);
            $table->unique(['project_id', 'type', 'team_id']);
        });

        Schema::create('private_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('thread_id')->constrained('private_chat_threads')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->boolean('is_hidden')->default(false);
            $table->timestamp('hidden_at')->nullable();
            $table->foreignId('hidden_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['thread_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('private_chat_messages');
        Schema::dropIfExists('private_chat_threads');
    }
};
