<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use NotificationChannels\Expo\ExpoMessage;

class NewPostPublished extends Notification
{
    public function __construct(public $post) {}

    public function via($notifiable)
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
