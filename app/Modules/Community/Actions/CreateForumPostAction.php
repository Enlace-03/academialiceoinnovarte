<?php

declare(strict_types=1);

namespace App\Modules\Community\Actions;

use App\Models\User;
use App\Modules\Community\Events\ForumPostCreated;
use App\Modules\Community\Models\ForumPost;
use App\Modules\Community\Models\ForumPostPhoto;
use App\Modules\Community\Models\ForumThread;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Respuestas de un solo nivel: si parent_post_id apunta a un post que ya es
 * en sí mismo una respuesta (tiene su propio parent_post_id), o a un post
 * oculto, se rechaza — la FK por sí sola no impide ninguna de las dos cosas.
 *
 * Fotos adjuntas: sin autorización propia (rechazado explícitamente en el
 * pedido) -- adjuntar es parte de crear el post, gobernado por el mismo
 * ForumPostPolicy::create() de siempre. MAX_PHOTOS_PER_POST es la autoridad
 * real (no solo la validación de UI en ForumThreadShow) porque es una regla
 * de negocio, no de presentación. El tamaño máximo por archivo SÍ vive solo
 * en la validación de Livewire (frontera de entrada del usuario) -- no hay
 * nada de negocio en "cuántos KB admite un input file".
 *
 * Cada foto llega como un objeto tipo Livewire\Features\SupportFileUploads\
 * TemporaryUploadedFile (duck-typed aquí como Illuminate\Http\File para no
 * acoplar este Action a Livewire): se guarda cruda con store(), y
 * CompressesPhotoUploads (mismo trait que GalleryPhoto) se encarga de
 * comprimirla al vuelo vía el evento created() de ForumPostPhoto.
 */
final class CreateForumPostAction
{
    public const MAX_PHOTOS_PER_POST = 4;

    /**
     * @param  array{content: string, parent_post_id?: int|null, photos?: array<\Illuminate\Http\UploadedFile>}  $data
     */
    public function execute(ForumThread $thread, User $author, array $data): ForumPost
    {
        $parentPostId = $data['parent_post_id'] ?? null;
        $photos = $data['photos'] ?? [];

        if ($parentPostId !== null) {
            $parentPost = ForumPost::findOrFail($parentPostId);

            if ($parentPost->parent_post_id !== null) {
                throw ValidationException::withMessages([
                    'parent_post_id' => 'Solo se permite un nivel de respuesta.',
                ]);
            }

            if ($parentPost->is_hidden) {
                throw ValidationException::withMessages([
                    'parent_post_id' => 'No se puede responder a un post oculto.',
                ]);
            }
        }

        if (count($photos) > self::MAX_PHOTOS_PER_POST) {
            throw ValidationException::withMessages([
                'photos' => 'Máximo '.self::MAX_PHOTOS_PER_POST.' fotos por publicación.',
            ]);
        }

        // DB::transaction(): si una foto falla el chequeo de memoria
        // disponible (CompressUploadedImageAction, dentro del evento
        // created() de ForumPostPhoto) a mitad del foreach, no debe quedar
        // un post creado con solo algunas de sus fotos -- todo o nada.
        // (No cubre el archivo YA comprimido en disco de una foto anterior
        // del mismo loop si una posterior falla; ese archivo queda
        // huérfano, caso raro y de bajo impacto, aceptado por ahora.)
        $post = DB::transaction(function () use ($thread, $author, $parentPostId, $data, $photos) {
            $post = ForumPost::create([
                'forum_thread_id' => $thread->id,
                'parent_post_id' => $parentPostId,
                'user_id' => $author->id,
                'content' => $data['content'],
            ]);

            foreach (array_values($photos) as $order => $photo) {
                ForumPostPhoto::create([
                    'forum_post_id' => $post->id,
                    'file_disk' => 'local',
                    'file_path' => $photo->store('forum-photos', 'local'),
                    'original_filename' => $photo->getClientOriginalName(),
                    'order' => $order,
                ]);
            }

            return $post;
        });

        event(new ForumPostCreated($post));

        return $post;
    }
}
