<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UbicacionActualizada implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $userId;
    public $userName;
    public $latitud;
    public $longitud;
    public $estado;

    public function __construct($userId, $userName, $latitud, $longitud, $estado)
    {
        $this->userId = $userId;
        $this->userName = $userName;
        $this->latitud = $latitud;
        $this->longitud = $longitud;
        $this->estado = $estado;
    }

    public function broadcastOn()
    {
        return new Channel('mapa');
    }

    public function broadcastAs()
    {
        return 'ubicacion.actualizada';
    }
}
