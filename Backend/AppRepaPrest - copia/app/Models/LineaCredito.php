<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['usuario_id', 'limite_aprobado', 'limite_disponible', 'estatus_id'])]
#[Hidden(['id'])]
#[Table('tbl_linea_credito')]

class LineaCredito extends Model
{
    public $timestamps = false;

    public function usuario()
    {
        return $this->belongsTo(User::class);
    }
    // public function
}
