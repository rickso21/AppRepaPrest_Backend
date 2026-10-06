<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PostDeleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Necesitamos guardar estos datos ANTES de borrar el post,
     * porque una vez eliminado no podemos acceder a $post->id ni $post->group_id.
     */
    public int $postId;
    public int $groupId;
    public int $userId;

    public function __construct(int $postId, int $groupId, int $userId)
    {
        $this->postId  = $postId;
        $this->groupId = $groupId;
        $this->userId  = $userId;

        \Log::info('[PostDeleted] Evento instanciado', [
            'post_id'  => $postId,
            'group_id' => $groupId,
            'user_id'  => $userId,
        ]);
    }

    /**
     * Canal privado del grupo.
     */
    public function broadcastOn(): array
    {
        $canalId = \App\Models\Grupo::canalPrincipalDe($this->groupId);
        return [new PrivateChannel('group.' . $canalId)];
    }

    /**
     * Nombre del evento en el frontend (Echo.listen('PostDeleted')).
     */
    public function broadcastAs(): string
    {
        return 'PostDeleted';
    }

    /**
     * Payload que recibe el cliente.
     */
    public function broadcastWith(): array
    {
        return [
            'id'       => $this->postId,
            'group_id' => $this->groupId,
            'user_id'  => $this->userId,
        ];
    }
}
