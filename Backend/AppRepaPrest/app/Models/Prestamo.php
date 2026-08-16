<?php

namespace App\Models;

use App\Models\LineaCredito;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['usuario_id', 'limite_credito_id', 'monto_solicitado', 'monto_total_pagar', 'numero_agos', 'periodicidad', 'fecha_solicitud', 'fecha_aprobacion', 'fecha_desembolso', 'fecha_primer_pago', 'estado_prestamo_id'])]
#[Hidden(['id'])]
#[Table('tbl_prestamo')]

class Prestamo extends Model
{
    public $timestamps = false;

    public function usuario()
    {
        return $this->belongsTo(User::class);
    }
    public function limite_credito()
    {
        return $this->belongsTo(LineaCredito::class);
    }
}
