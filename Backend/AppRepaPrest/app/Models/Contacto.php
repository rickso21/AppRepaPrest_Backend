<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contacto extends Model
{
      protected $table = 'tbl_contactos';

    // Tu tabla usa "creado_en" y "actualizado_en" en lugar de los
    // timestamps por defecto (created_at, updated_at)
    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'nombre',
        'correo',
        'pais',
        'prefijo',
        'telefono',
        'mensaje',
        'ip_origen',
        'user_agent',
        'estado',
    ];

    protected $casts = [
        'creado_en'      => 'datetime',
        'actualizado_en' => 'datetime',
    ];
}
