<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

// Hito 3b-3: Submission pasa de un archivo escalar (file_disk/file_path/
// original_filename, migración 000210) a una tabla hija de adjuntos --
// mismo patrón que gallery_photos/forum_post_photos -- para admitir varios
// adjuntos por entrega (foto + enlaces, incluido YouTube). type: photo | link.
// Sin softDeletes (igual que gallery_photos/forum_post_photos): es un hijo,
// un delete siempre es físico.
//
// La copia de datos (paso 2 de up()) usa DB::table()->insert() crudo, NUNCA
// Eloquent -- las submissions con file_path de antes de este hito pudieron
// subirse sin restricción de tipo (el FileUpload del docente no exigía
// ->image()), así que podrían no ser imágenes reales; pasar por el modelo
// dispararía CompressUploadedImageAction, que fallaría o decodificaría
// basura sobre un PDF. Migrar tal cual preserva el archivo exacto, sin
// intentar re-comprimir datos históricos.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submission_attachments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10);
            $table->string('file_disk', 30)->nullable();
            $table->string('file_path', 191)->nullable();
            $table->string('original_filename', 191)->nullable();
            $table->string('url', 500)->nullable();
            $table->boolean('is_youtube')->default(false);
            $table->unsignedTinyInteger('order')->default(0);
            $table->timestamps();
        });

        DB::table('submissions')
            ->whereNotNull('file_path')
            ->orderBy('id')
            ->get(['id', 'file_disk', 'file_path', 'original_filename'])
            ->each(function (object $submission): void {
                DB::table('submission_attachments')->insert([
                    'uuid' => (string) Str::uuid(),
                    'submission_id' => $submission->id,
                    'type' => 'photo',
                    'file_disk' => $submission->file_disk,
                    'file_path' => $submission->file_path,
                    'original_filename' => $submission->original_filename,
                    'url' => null,
                    'is_youtube' => false,
                    'order' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn(['file_disk', 'file_path', 'original_filename']);
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->string('file_disk', 30)->nullable()->after('text_content');
            $table->string('file_path', 191)->nullable()->after('file_disk');
            $table->string('original_filename', 191)->nullable()->after('file_path');
        });

        // Best-effort: solo reversible fielmente para adjuntos que vinieron
        // de esta misma migración (type=photo, order=0) -- adjuntos nuevos
        // o múltiples agregados después de aplicar up() no tienen forma de
        // volver a una sola columna escalar sin perder datos. Suficiente
        // para el checklist de migration-conventions (down() probado justo
        // después de up()), no para sobrevivir cambios posteriores.
        DB::table('submission_attachments')
            ->where('type', 'photo')
            ->where('order', 0)
            ->orderBy('id')
            ->get(['submission_id', 'file_disk', 'file_path', 'original_filename'])
            ->each(function (object $attachment): void {
                DB::table('submissions')
                    ->where('id', $attachment->submission_id)
                    ->update([
                        'file_disk' => $attachment->file_disk,
                        'file_path' => $attachment->file_path,
                        'original_filename' => $attachment->original_filename,
                    ]);
            });

        Schema::dropIfExists('submission_attachments');
    }
};
