<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Registro de delegaciones de permisos: quién otorgó qué permiso a quién,
// con alcance opcional y auditoría. Complementa a spatie/laravel-permission:
// spatie guarda el "¿tiene el permiso?" (booleano); esta tabla guarda el
// contexto (quién lo dio, con qué alcance, cuándo, si expira).
//
// Especialmente necesaria para permisos con ALCANCE, como students.create.scoped:
// el scope define SOBRE QUÉ estudiantes puede actuar (todos, o ciertos grupos).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('granted_by')->constrained('users')->cascadeOnDelete();
            $table->string('permission'); // clave del catálogo, ej. 'students.create.scoped'

            // Alcance del permiso. null = sin restricción de alcance (permiso normal).
            // Ejemplos para students.create.scoped:
            //   {"type": "all"}                       → cualquier estudiante de la institución
            //   {"type": "groups", "group_ids": [3,5]} → solo esos grupos
            $table->json('scope')->nullable();

            $table->timestamp('expires_at')->nullable(); // permiso temporal opcional
            $table->timestamps();

            $table->index(['user_id', 'permission']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_grants');
    }
};
