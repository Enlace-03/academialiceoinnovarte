<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Fotos adjuntas en ForumPost. Mismo patrón de columnas que gallery_photos
// (file_disk/file_path/original_filename/order/uuid) a propósito -- ambas
// pasan por la misma compresión (ver CompressesPhotoUploads). Sin
// autorización propia: adjuntar es parte de crear el post, gobernado por
// ForumPostPolicy::create() ya existente.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forum_post_photos', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('forum_post_id')->constrained()->cascadeOnDelete();
            $table->string('file_disk', 30);
            $table->string('file_path', 191);
            $table->string('original_filename', 191)->nullable();
            $table->unsignedTinyInteger('order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_post_photos');
    }
};
