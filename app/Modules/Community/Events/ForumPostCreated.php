<?php

declare(strict_types=1);

namespace App\Modules\Community\Events;

use App\Modules\Community\Models\ForumPost;
use Illuminate\Queue\SerializesModels;

/**
 * Disparado por CreateForumPostAction (post raíz o respuesta, ambos
 * cuentan como participación), sin cambiar su comportamiento existente.
 */
final class ForumPostCreated
{
    use SerializesModels;

    public function __construct(public readonly ForumPost $post) {}
}
