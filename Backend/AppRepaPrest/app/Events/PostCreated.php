<?php

namespace App\Events;

use App\Models\Post;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PostCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Post $post)
    {
    }

    public function broadcastOn(): array
    {
        $canalId = \App\Models\Grupo::canalPrincipalDe((int) $this->post->group_id);

        return [new PrivateChannel('group.' . $canalId)];
    }

    public function broadcastAs(): string
    {
        return 'post.created';   // ✅ con namespace
    }

    public function broadcastWith(): array
    {
        return [
            'id'         => $this->post->id,
            'post'       => $this->post->post,
            'image'      => $this->post->image,
            'user_id'    => $this->post->user_id,
            'group_id'   => $this->post->group_id,
            'created_at' => $this->post->created_at?->toISOString(),
        ];
    }
}
