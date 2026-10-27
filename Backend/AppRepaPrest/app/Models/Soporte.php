<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Soporte extends Model
{
    protected $table = 'tbl_soporte';

    protected $fillable = [
        'nombre',
        'telefono',
        'mensaje_default',
        'horario',
        'email',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];
}
