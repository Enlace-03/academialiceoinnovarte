<?php

declare(strict_types=1);

namespace App\Modules\Community\Actions;

use App\Models\User;
use App\Modules\Community\Events\PrivateChatMessageSent;
use App\Modules\Community\Models\PrivateChatMessage;
use App\Modules\Community\Models\PrivateChatThread;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectTeam;

/**
 * El hilo no se crea con un paso separado (decisión confirmada): se
 * resuelve o crea acá mismo, la primera vez que alguien de cualquiera de
 * las dos partes válidas envía un mensaje a un contexto autorizado
 * (proyecto+estudiante, o proyecto+equipo). firstOrCreate() sobre la
 * combinación única evita duplicar el hilo en envíos repetidos -- la
 * autorización del contexto (¿puede esta persona participar acá?) es
 * responsabilidad del caller vía
 * Gate::authorize('create', [PrivateChatThread::class, $project, $type,
 * $student, $team]), no de esta Action.
 */
final class SendPrivateChatMessageAction
{
    public function execute(
        Project $project,
        string $type,
        ?User $student,
        ?ProjectTeam $team,
        User $author,
        string $content,
    ): PrivateChatMessage {
        $thread = PrivateChatThread::query()->firstOrCreate([
            'project_id' => $project->id,
            'type' => $type,
            'student_id' => $type === 'individual' ? $student?->id : null,
            'team_id' => $type === 'team' ? $team?->id : null,
        ]);

        $message = $thread->messages()->create([
            'user_id' => $author->id,
            'content' => $content,
        ]);

        event(new PrivateChatMessageSent($message));

        return $message;
    }
}
