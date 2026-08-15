<?php

declare(strict_types=1);

namespace App\Modules\Community\Events;

use App\Modules\Community\Models\PrivateChatMessage;
use Illuminate\Queue\SerializesModels;

/**
 * Disparado por SendPrivateChatMessageAction, sin excepción -- incluye el
 * primer mensaje de un hilo recién creado, no solo respuestas a un hilo
 * existente (a diferencia de ForumPostCreated, acá no hay distinción entre
 * "hilo nuevo" y "actividad en hilo existente": el listener de
 * notificación decide a quién avisar mirando el propio hilo, no el evento).
 */
final class PrivateChatMessageSent
{
    use SerializesModels;

    public function __construct(public readonly PrivateChatMessage $message) {}
}
