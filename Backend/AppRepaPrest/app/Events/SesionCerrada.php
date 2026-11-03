<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;


class SesionCerrada implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $userId,
        public string $reason = 'session_replaced',
    ) {}

    /**
     * Canal privado del usuario.
     * Nombre: private-user.{id}
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("user.{$this->userId}")];
    }

    /**
     * Nombre del evento en el cliente.
     */
    public function broadcastAs(): string
    {
        return 'SesionCerrada';
    }

    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->userId,
            'reason'  => $this->reason,
            'ts'      => now()->toIso8601String(),
        ];
    }
}
