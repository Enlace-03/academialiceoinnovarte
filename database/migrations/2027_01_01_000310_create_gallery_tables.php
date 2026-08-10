<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Galería de fotos (institucional/por proyecto). project_id nullable: null =
// contenido general/institucional, visible para todos; con valor, hereda la
// audiencia del proyecto (mismo criterio que User::canAccessProject()) —
// ver GalleryPostPolicy. Mismo patrón pragmático de archivo que
// submissions/forum_post_photos (file_disk/file_path/original_filename),
// sin conectar MediaLibrary.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gallery_posts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 191);
            $table->text('caption')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['project_id', 'published_at']);
        });

        Schema::create('gallery_photos', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('gallery_post_id')->constrained()->cascadeOnDelete();
            $table->string('file_disk', 30);
            $table->string('file_path', 191);
            $table->string('original_filename', 191)->nullable();
            $table->unsignedTinyInteger('order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_photos');
        Schema::dropIfExists('gallery_posts');
    }
};
