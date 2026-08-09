<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    /**
     * Idempotencia del recordatorio de entrega (Hito 5b): una fila por
     * (schedule, umbral) marca que ese recordatorio ya se envió, sin
     * importar cuántas veces corra el job ese día ni cuántos destinatarios
     * reciba (estudiante directo o varios acudientes) — el evento en sí
     * ("faltan 3 días para la fase X del estudiante Y") es lo que no se
     * duplica, no cada envío individual.
     */
    public function up(): void
    {
        Schema::create('sent_deadline_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_phase_schedule_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('threshold_days');
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->unique(['student_phase_schedule_id', 'threshold_days'], 'sent_deadline_reminders_schedule_threshold_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sent_deadline_reminders');
    }
};
