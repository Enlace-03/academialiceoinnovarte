<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Boletines en PDF, generados por GenerateReportCardJob y guardados en el
// disco privado 'local'. SIN period_id a propósito: este colegio no tiene
// periodos académicos (evaluación continua, ver PEI). type:
//   'parcial' -> corte informal en cualquier momento, no se congela; puede
//                haber varios por estudiante.
//   'total'   -> cierre de un grado: uno por (student_id, school_grade_id,
//                academic_year). MySQL/MariaDB no tienen índice único
//                parcial, así que esa unicidad la hace cumplir el job (en
//                una transacción), no la base.
// student_id y school_grade_id usan restrictOnDelete(): es un documento
// oficial con datos de un menor y un archivo en disco -- un cascade de BD no
// dispara el hook que borra el archivo y lo dejaría huérfano.
// generated_by es nullable (nullOnDelete): el job puede correr sin un usuario.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_cards', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('school_grade_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('academic_year');
            $table->string('type', 10); // parcial | total
            $table->string('file_disk', 30);
            $table->string('file_path', 191);
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at');
            $table->timestamps();

            $table->index(['student_id', 'type']);
            $table->index(['student_id', 'school_grade_id', 'academic_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_cards');
    }
};
