<?php

namespace App\Models;

use App\Models\LineaCredito;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'folio',                    // ← Agregar folio
    'usuario_id', 
    'linea_credito_id',         // ← Corregir: linea_credito_id
    'monto_solicitado', 
    'monto_total_pagar', 
    'numero_pagos',             // ← Corregir: numero_pagos
    'periodicidad', 
    'fecha_solicitud', 
    'fecha_aprobacion', 
    'fecha_desembolso', 
    'fecha_primer_pago', 
    'estado_prestamo_id'
])]
#[Hidden(['id'])]
#[Table('tbl_prestamo')]

class Prestamo extends Model
{
    public $timestamps = false;

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id', 'id');
    }
    
    public function lineaCredito()  // ← Cambiar nombre a lineaCredito
    {
        return $this->belongsTo(LineaCredito::class, 'linea_credito_id', 'id');
    }
    
    // También puedes agregar la relación con estado
    public function estado()
    {
        return $this->belongsTo(EstadoPrestamo::class, 'estado_prestamo_id', 'id');
    }
}