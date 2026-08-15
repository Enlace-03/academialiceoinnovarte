<?php

declare(strict_types=1);

namespace App\Modules\Communication\Notifications;

use App\Modules\Community\Models\PrivateChatMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Lado estudiante del chat privado (individual o de equipo) -- mismo canal
 * que ForumReplyReceived (mail+database), destinatario siempre alguien con
 * acceso directo al portal de estudiante (el propio estudiante del hilo
 * individual, o cada integrante del equipo), nunca un acudiente: los
 * acudientes no tienen ninguna vía de acceso a este chat (sin rama en
 * PrivateChatThreadPolicy), así que enviarles esta notificación dejaría un
 * deep link que no pueden abrir.
 */
final class PrivateChatMessageReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly PrivateChatMessage $message) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $thread = $this->message->thread;
        $label = $thread->type === 'individual' ? 'chat privado' : 'chat de equipo';

        return (new MailMessage())
            ->subject('Nuevo mensaje — Liceo Innovarte')
            ->greeting('Hola '.$notifiable->name)
            ->line("{$this->message->user->name} te escribió en el {$label} de \"{$thread->project->title}\".")
            ->line('"'.Str::limit($this->message->content, 200).'"')
            ->action('Ir a la conversación', $this->actionUrl())
            ->salutation('Saludos, Academia Liceo Innovarte');
    }

    public function toArray(object $notifiable): array
    {
        $thread = $this->message->thread;

        return [
            'type' => 'private_chat_message_received',
            'private_chat_message_id' => $this->message->id,
            'thread_type' => $thread->type,
            'author_name' => $this->message->user->name,
            'project_title' => $thread->project->title,
            'action_url' => $this->actionUrl(),
        ];
    }

    private function actionUrl(): string
    {
        $thread = $this->message->thread;

        // 'chat-individual' / 'chat-team' -- coincide 1:1 con el id del
        // contenedor en project-show.blade.php, que a su vez coincide con
        // PrivateChatThread::TYPES (nunca traducido a español acá para no
        // arriesgar un desacople silencioso entre los tres lugares).
        return route('student.projects.show', $thread->project->uuid).'#chat-'.$thread->type;
    }
}
