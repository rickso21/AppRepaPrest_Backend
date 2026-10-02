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
         \Log::info('[CommentCreated] Evento instanciado', [
        'comment_id' => $comment->id,
        'post_id' => $comment->post_id,
        'user_id' => $comment->user_id,
        'timestamp' => now()->toISOString(),
    ]);
    }

    public function broadcastOn(): array
    {
        // Obtener el grupo del post asociado al comentario
        $groupId = $this->comment->post?->group_id;

          \Log::info('[CommentCreated] broadcastOn llamado', [
        'comment_id' => $this->comment->id,
    ]);

        return [
            new PrivateChannel('group.' . $groupId),
              new PrivateChannel('global'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'CommentCreated';
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
