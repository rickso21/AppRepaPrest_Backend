<?php

namespace App\Events;

use App\Models\Post;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PostReacted implements ShouldBroadcast, ShouldQueue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $postId;
    public $groupId;        // ← nuevo
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
        $this->groupId      = $post->group_id;   // ← guardar aquí
        $this->userId       = $userId;
        $this->type         = $type;
        $this->accion       = $accion;
        $this->tipoAnterior = $tipoAnterior;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('group.' . $this->groupId)];
    }

    public function broadcastAs(): string
    {
        return 'PostReacted';
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
