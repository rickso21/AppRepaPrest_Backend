<?php

namespace App\Events;

use App\Models\Comment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CommentCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Comment $comment)
    {
    }

    public function broadcastOn(): array
    {
        $groupId = $this->comment->post?->group_id;
        $canalId = \App\Models\Grupo::canalPrincipalDe((int) $groupId);

        $canales = [];

        // Canal del grupo real del post (emergencia / monitoreo / principal)
        if ($groupId) {
            $canales[] = new PrivateChannel('group.' . $groupId);
        }

        // Canal principal (para que el principal reciba también lo de sus secundarios)
        if ($canalId && $canalId !== $groupId) {
            $canales[] = new PrivateChannel('group.' . $canalId);
        }

        // Canal global (lo que ya tenías)
        $canales[] = new PrivateChannel('global');

        return $canales;
    }

    public function broadcastAs(): string
    {
        return 'comment.created';   // ✅ con namespace
    }

    public function broadcastWith(): array
    {
        return [
            'id'         => $this->comment->id,
            'post_id'    => $this->comment->post_id,
            'user_id'    => $this->comment->user_id,
            'comment'    => $this->comment->comment,
            'created_at' => $this->comment->created_at?->toISOString(),
        ];
    }
}
