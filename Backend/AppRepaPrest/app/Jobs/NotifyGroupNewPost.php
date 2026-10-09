<?php

namespace App\Jobs;

use App\Models\Post;
use App\Models\User;
use App\Notifications\NewPostPublished;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifyGroupNewPost implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        public int $postId,
        public int $groupId,
        public int $authorId,
    ) {}

    public function handle(): void
    {
        $post = Post::find($this->postId);
        if (!$post) return;

        User::where('grupo_id', $this->groupId)
            ->where('id', '!=', $this->authorId)
            ->select('id')
            ->chunkById(500, function ($users) use ($post) {
                foreach ($users as $user) {
                    $user->notify(new NewPostPublished($post));
                }
            });
    }
}
