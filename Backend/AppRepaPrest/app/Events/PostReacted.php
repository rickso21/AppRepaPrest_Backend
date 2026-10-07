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

        \Log::info('[PostReacted] Evento instanciado', [
            'post_id'       => $post->id,
            'group_id'      => $post->group_id,
            'user_id'       => $userId,
            'type'          => $type,
            'accion'        => $accion,
            'tipoAnterior'  => $tipoAnterior,
            'timestamp'     => now()->toISOString(),
        ]);
    }

    public function broadcastOn(): array
    {
        $groupId = Post::find($this->postId)?->group_id;

        \Log::info('[PostReacted] broadcastOn llamado', [
            'post_id'  => $this->postId,
            'groupId'  => $groupId,
            'canal'    => 'group.' . $groupId,
        ]);

        return [
            new PrivateChannel('group.' . $groupId),
        ];
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
