<?php

declare(strict_types=1);

namespace App\Modules\Communication\Notifications;

use App\Modules\Communication\Support\FormatsStudentLabel;
use App\Modules\Community\Models\ForumPost;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Solo para respuestas directas (parent_post_id no nulo) al post del propio
 * destinatario -- nunca por actividad general del hilo (decisión confirmada
 * del Hito 5a). Disparada por NotifyForumReplyAuthor, que ya filtra el caso
 * de auto-respuesta antes de instanciar esta clase.
 */
final class ForumReplyReceived extends Notification implements ShouldQueue
{
    use Queueable, FormatsStudentLabel;

    public function __construct(public readonly ForumPost $reply) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Nueva respuesta en el foro — Liceo Innovarte')
            ->greeting('Hola '.$notifiable->name)
            ->line("{$this->studentLabel($this->reply->user)} respondió a tu publicación en el foro.")
            ->line('"'.Str::limit($this->reply->content, 200).'"')
            ->line('Ingresa a la plataforma para ver la conversación completa.')
            ->salutation('Saludos, Academia Liceo Innovarte');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'forum_reply_received',
            'forum_post_id' => $this->reply->id,
            'forum_thread_id' => $this->reply->forum_thread_id,
            'author_name' => $this->studentLabel($this->reply->user),
        ];
    }
}
