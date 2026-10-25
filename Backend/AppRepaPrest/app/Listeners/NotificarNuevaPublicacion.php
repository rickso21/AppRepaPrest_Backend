<?php

namespace App\Listeners;

use App\Events\PostCreated;
use App\Models\User;
use App\Models\Grupo;
use App\Services\PushNotificationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class NotificarNuevaPublicacion
{
    // Se ejecuta sincrónicamente (no hay ShouldQueue)
    //    Los jobs se procesan al instante, sin necesidad de worker.

    public function __construct(
        protected PushNotificationService $push
    ) {}

    public function handle(PostCreated $event): void
    {
        // Log de debug al inicio para confirmar que se ejecuta
        Log::info('[push.publicacion] handle() invocado', [
            'post_id' => $event->post->id ?? null,
        ]);

        try {
            $post = $event->post ?? null;
            if (!$post) return;

            $autor = $post->user;
            if (!$autor) return;

            // Miembros del grupo (activos, con token, excepto el autor)
            $grupoPrincipalId = Grupo::canalPrincipalDe((int) $post->group_id);
$gruposVisibles   = Grupo::gruposVisiblesDe($grupoPrincipalId);

$destinatarios = User::whereIn('grupo_id', $gruposVisibles)
    ->where('status_id', 1)
    ->whereNotNull('expo_push_token')
    ->where('id', '!=', $autor->id)
    ->get();

            Log::info('[push.publicacion] Destinatarios encontrados', [
                'total'  => $destinatarios->count(),
                'grupo'  => $post->group_id,
            ]);

            if ($destinatarios->isEmpty()) return;

             $enviados = $this->push->sendToUsers(
                users: $destinatarios,
                title: 'Nueva Notificacion', // Título principal
                body:  "📢Un miembro ha publicado\n" . $autor->full_name . ': ' . Str::limit($post->post ?? 'Adjunto', 50),
                data:  [
                    'screen' => 'Comunidad',
                    'postId' => $post->id,
                ],
                excludeUserId: $autor->id
            );



            Log::info('[push.publicacion] Enviado', [
                'post_id'  => $post->id,
                'enviados' => $enviados,
            ]);
        } catch (\Throwable $e) {
            Log::error('[push.publicacion] Error', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);
        }
    }
}
