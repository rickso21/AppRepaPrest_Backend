<?php

namespace App\Models;

use App\Models\LineaCredito;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;



#[Fillable([
    'folio',
    'usuario_id', 
    'linea_credito_id',
    'monto_solicitado', 
    'monto_total_pagar',
    'monto_restante', // ← NUEVO
    'numero_pagos',
    'pagos_realizados', // ← NUEVO
    'periodicidad', 
    'fecha_solicitud', 
    'fecha_aprobacion', 
    'fecha_desembolso', 
    'fecha_primer_pago',
    'fecha_ultimo_pago', // ← NUEVO
    'estado_prestamo_id',
    'incremento_aplicado',
    'fecha_incremento',
    'ruta_pdf_aprobacion',
    'ruta_pdf_liquidacion', 
    'ruta_pdf_rechazo'
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

    public function pagos()
{
    return $this->hasMany(Pago::class, 'prestamo_id', 'id');
}
}