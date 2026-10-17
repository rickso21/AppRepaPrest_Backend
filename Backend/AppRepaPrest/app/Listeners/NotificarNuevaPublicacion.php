<?php

namespace App\Listeners;

use App\Events\PostCreated;
use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class NotificarNuevaPublicacion
{
    // ⚡ Se ejecuta en background, no bloquea el request
    public string $queue = 'notifications';

    public function __construct(
        protected PushNotificationService $push
    ) {}

    public function handle(PostCreated $event): void
    {
        try {
            $post = $event->post ?? null;
            if (!$post) return;

            $autor = $post->user;
            if (!$autor) return;

            // Miembros del grupo (activos, con token, excepto el autor)
            $destinatarios = User::where('grupo_id', $post->group_id)
                ->where('status_id', 1)
                ->whereNotNull('expo_push_token')
                ->where('id', '!=', $autor->id)
                ->get();

            if ($destinatarios->isEmpty()) return;

            $this->push->sendToUsers(
                users: $destinatarios,
                title: '📢 Nueva publicación',
                body:  $autor->full_name . ': ' . Str::limit($post->post ?? 'Nueva publicación', 60),
                data:  [
                    'screen' => 'Comunidad',
                    'postId' => $post->id,
                ],
                excludeUserId: $autor->id
            );
        } catch (\Throwable $e) {
            Log::warning('[push.publicacion] Error: ' . $e->getMessage());
        }
    }
}
