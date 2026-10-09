<?php

namespace App\Notifications;

use App\Models\Post;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Expo\ExpoMessage;

class NewPostPublished extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public $post) {}

    public function via($notifiable): array
    {
        return ['expo'];
    }

    public function toExpo($notifiable)
    {
        return ExpoMessage::create('Nuevo post en tu grupo')
            ->body($this->post->user->nombre . ' ha publicado algo.')
            ->data([
                'screen' => 'Comunidad',
                'postId' => $this->post->id,
            ])
            ->high();
    }
}
