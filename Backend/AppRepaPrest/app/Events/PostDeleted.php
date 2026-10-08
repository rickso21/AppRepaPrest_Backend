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

    public int $postId;
    public int $groupId;
    public int $userId;

    public function __construct(int $postId, int $groupId, int $userId)
    {
        $this->postId  = $postId;
        $this->groupId = $groupId;
        $this->userId  = $userId;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('group.' . $this->groupId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'post.deleted';   // ✅ con namespace
    }

    public function broadcastWith(): array
    {
        return [
            'id'       => $this->postId,
            'group_id' => $this->groupId,
            'user_id'  => $this->userId,
        ];
    }
}
