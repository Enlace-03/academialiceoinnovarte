<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Hito 2: mecanismo de archivo para submissions de tipo "archivo". Se verificó
// que Spatie MediaLibrary no está conectado en ningún modelo del proyecto
// (solo está en composer.json) — se usa el mismo patrón pragmático que
// resources.url_or_path del Hito 1C, migrable a MediaLibrary después sin
// romper nada si se necesitan miniaturas o múltiples archivos.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->string('file_disk', 30)->nullable()->after('text_content');
            $table->string('file_path', 191)->nullable()->after('file_disk');
            $table->string('original_filename', 191)->nullable()->after('file_path');
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn(['file_disk', 'file_path', 'original_filename']);
        });
    }
};
