<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_session_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('granted_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();

            // Consulta frecuente: "la entrega más reciente sin cerrar de este estudiante"
            // (mecanismo de respaldo del cierre si session('active_grant_id') no está disponible).
            $table->index(['student_id', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_session_grants');
    }
};
