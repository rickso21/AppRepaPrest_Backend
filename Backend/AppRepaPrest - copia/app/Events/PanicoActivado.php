<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class PanicoActivado implements ShouldBroadcast
{
    public $userId;
    public $userName;
    public $latitud;
    public $longitud;
    public $tipoEmergencia;
    public $alertaId;

    public function __construct($userId, $userName, $latitud, $longitud, $tipoEmergencia, $alertaId)
    {
        $this->userId = $userId;
        $this->userName = $userName;
        $this->latitud = $latitud;
        $this->longitud = $longitud;
        $this->tipoEmergencia = $tipoEmergencia;
        $this->alertaId = $alertaId;
    }

    public function broadcastOn()
    {
        return new Channel('mapa');
    }

    public function broadcastAs()
    {
        return 'panico.activado';
    }

    public function broadcastWith()
    {
        return [
            'userId' => $this->userId,
            'userName' => $this->userName,
            'latitud' => $this->latitud,
            'longitud' => $this->longitud,
            'tipoEmergencia' => $this->tipoEmergencia,
            'alertaId' => $this->alertaId,
            'timestamp' => now()->toISOString()
        ];
    }
}
