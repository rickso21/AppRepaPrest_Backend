<?php

namespace App\Listeners;

use App\Events\CommentCreated;
use App\Services\PushNotificationService;
use Illuminate\Support\Facades\Log;

class NotificarNuevoComentario
{

    public function __construct(
        protected PushNotificationService $push
    ) {}

    public function handle(CommentCreated $event): void
    {
        // Log de debug al inicio
        Log::info('[push.comment] handle() invocado', [
            'comment_id' => $event->comment->id ?? null,
        ]);

        try {
            $comment = $event->comment ?? null;
            if (!$comment) return;

            $post = $comment->post;
            if (!$post) return;

            $autor     = $comment->user;
            $postAutor = $post->user;

            if (!$postAutor || !$autor) return;
            if ($postAutor->id === $autor->id) return;      // No auto-notificar
            if (empty($postAutor->expo_push_token)) return; // Sin token

            $enviado = $this->push->sendToUser(
                user:  $postAutor,
                title: '💬 Nuevo comentario',
                body:  $autor->full_name . ' comentó tu publicación',
                data:  [
                    'screen' => 'Comunidad',
                    'postId' => $post->id,
                ]
            );

            Log::info('[push.comment] Enviado', [
                'post_id'  => $post->id,
                'user_id'  => $postAutor->id,
                'enviado'  => $enviado,
            ]);
        } catch (\Throwable $e) {
            Log::error('[push.comment] Error', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);
        }
    }
}
