<?php

namespace App\Events;

use App\Models\Prestamo;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PrestamoActualizado
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Prestamo $prestamo,
        public int $estadoAnterior,
        public int $estadoNuevo
    ) {
    }
}
