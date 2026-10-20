<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

// ShouldBroadcastNow = sale directo a Reverb, sin depender de un queue worker.
// Canal PRIVADO por grupo: solo lo reciben los miembros del grupo.
class PanicoDesactivado implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public int $grupoId, public int $userId, public int $alertaId,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('grupo.' . $this->grupoId)];
    }

    public function broadcastAs(): string
    {
        return 'panico.desactivado';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->userId, 'alertaId' => $this->alertaId,
        ];
    }
}
