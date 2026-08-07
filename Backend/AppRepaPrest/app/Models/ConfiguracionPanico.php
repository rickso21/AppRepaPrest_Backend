<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConfiguracionPanico extends Model
{
    protected $table = 'tbl_configuracion_panico';
    public $timestamps = false;

    protected $fillable = [
        'usuario_id',
        'recibir_alertas',
        'notificacion_push',
        'notificacion_whatsapp',
        'notificacion_correo',
        'radio_alerta_km',
        'fecha_actualizacion'
    ];

    protected $casts = [
        'recibir_alertas' => 'boolean',
        'notificacion_push' => 'boolean',
        'notificacion_whatsapp' => 'boolean',
        'notificacion_correo' => 'boolean',
        'radio_alerta_km' => 'decimal:2',
        'fecha_actualizacion' => 'datetime'
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
