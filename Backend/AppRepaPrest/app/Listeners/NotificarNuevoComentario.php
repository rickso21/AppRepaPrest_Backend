<?php

namespace App\Listeners;

use App\Events\CommentCreated;
use App\Services\PushNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class NotificarNuevoComentario implements ShouldQueue
{
    public string $queue = 'notifications';

    public function __construct(
        protected PushNotificationService $push
    ) {}

    public function handle(CommentCreated $event): void
    {
        try {
            $comment = $event->comment ?? null;
            if (!$comment) return;

            $post = $comment->post;
            if (!$post) return;

            $autor = $comment->user;
            $postAutor = $post->user;

            if (!$postAutor || !$autor) return;
            if ($postAutor->id === $autor->id) return; // No auto-notificar
            if (empty($postAutor->expo_push_token)) return;

            $this->push->sendToUser(
                user:  $postAutor,
                title: '💬 Nuevo comentario',
                body:  $autor->full_name . ' comentó tu publicación',
                data:  [
                    'screen' => 'Comunidad',
                    'postId' => $post->id,
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('[push.comment] Error: ' . $e->getMessage());
        }
    }
}
