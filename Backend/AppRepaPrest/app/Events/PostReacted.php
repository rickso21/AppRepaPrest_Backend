<?php

namespace App\Events;

use App\Models\Post;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PostReacted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $postId;
    public $userId;
    public $type;
    public $accion;
    public $tipoAnterior;

    public function __construct(
        Post $post,
        int $userId,
        ?string $type,
        string $accion,
        ?string $tipoAnterior = null
    ) {
        $this->postId       = $post->id;
        $this->userId       = $userId;
        $this->type         = $type;
        $this->accion       = $accion;
        $this->tipoAnterior = $tipoAnterior;
    }

    public function broadcastOn(): array
    {
        $groupId = Post::find($this->postId)?->group_id;

        return [
            new PrivateChannel('group.' . $groupId),
        ];
    }

    /**
     * ✅ Nombre del evento con namespace (obligatorio para Pusher-js + Reverb).
     * Pusher-js SOLO reconoce eventos de aplicación si empiezan con punto.
     * El `.` se agrega implícitamente al bindear en el cliente.
     */
    public function broadcastAs(): string
    {
        return 'post.reacted';
    }

    public function broadcastWith(): array
    {
        return [
            'postId'       => $this->postId,
            'userId'       => $this->userId,
            'type'         => $this->type,
            'accion'       => $this->accion,
            'tipoAnterior' => $this->tipoAnterior,
        ];
    }
}
