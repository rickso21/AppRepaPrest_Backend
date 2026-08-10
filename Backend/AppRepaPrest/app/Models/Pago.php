<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pago extends Model
{
    protected $table = 'tbl_pagos';

    protected $fillable = [
        'prestamo_id',
        'usuario_id',
        'monto_pagado',
        'monto_restante',
        'tipo_pago',
        'fecha_pago',
        'referencia',
        'observaciones'
    ];

    protected $casts = [
        'monto_pagado' => 'decimal:2',
        'monto_restante' => 'decimal:2',
        'fecha_pago' => 'datetime'
    ];

    public function prestamo()
    {
        return $this->belongsTo(Prestamo::class, 'prestamo_id', 'id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id', 'id');
    }
}