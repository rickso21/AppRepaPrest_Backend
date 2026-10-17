<?php

namespace App\Events;

use App\Models\Prestamo;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PrestamoSolicitado
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Prestamo $prestamo
    ) {
    }
}
