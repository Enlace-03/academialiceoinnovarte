<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Configuración institucional editable (reemplaza valores fijos como el año lectivo
// vigente, hasta ahora atados a config/school.php).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institution_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('key', 60);
            $table->string('value')->nullable();
            $table->timestamps();
            $table->unique(['institution_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institution_settings');
    }
};
