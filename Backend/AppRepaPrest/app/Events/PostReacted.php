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

    public function __construct(
        public Post $post,
        public int $userId,
        public ?string $tipoFinal,
        public string $accion,
        public ?string $tipoAnterior = null,
    ) {
    }

    public function broadcastOn(): array
    {
        $groupId = (int) $this->post->group_id;
        $canalId = \App\Models\Grupo::canalPrincipalDe($groupId);

        $canales = [new PrivateChannel('group.' . $groupId)];

        if ($canalId && $canalId !== $groupId) {
            $canales[] = new PrivateChannel('group.' . $canalId);
        }

        return $canales;
    }

    public function broadcastAs(): string
    {
        return 'post.reacted';
    }

    public function broadcastWith(): array
    {
        return [
            'post_id'       => $this->post->id,
            'user_id'       => $this->userId,
            'tipo_final'    => $this->tipoFinal,
            'accion'        => $this->accion,
            'tipo_anterior' => $this->tipoAnterior,
            'group_id'      => $this->post->group_id,
            'created_at'    => now()->toISOString(),
        ];
    }
}
