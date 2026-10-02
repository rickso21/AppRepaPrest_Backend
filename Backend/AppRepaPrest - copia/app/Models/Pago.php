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
        'saldo_antes_pago',      // ← FALTABA
        'tipo_pago',
        'es_adelantado',          // ← FALTABA
        'numero_quincena',        // ← FALTABA
        'fecha_pago',
        'referencia',
        'observaciones',
        'metodo_pago',            // ← FALTABA
        'usuario_registro',       // ← FALTABA
        'estado_pago',            // ← FALTABA
        'fecha_confirmacion',     // ← FALTABA
        'fecha_desembolso_original', // ← FALTABA
        'status',
        'preference_id',          // ← FALTABA
        'no_pago_mp',            // ← FALTABA
        'payment_method',         // ← FALTABA
        'fecha_verificacion',     // ← FALTABA
        'url_pdf',               // ← FALTABA
        'usuario_confirmo',       // ← FALTABA
        'status_mp',              // ← FALTABA

    ];

    protected $casts = [
        'monto_pagado' => 'decimal:2',
        'monto_restante' => 'decimal:2',
        'saldo_antes_pago' => 'decimal:2',
        'es_adelantado' => 'boolean',
        'status' => 'integer',
        'fecha_pago' => 'datetime',
        'fecha_confirmacion' => 'datetime',
        'fecha_verificacion' => 'datetime',
        'fecha_desembolso_original' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Relaciones
    public function prestamo()
    {
        return $this->belongsTo(Prestamo::class, 'prestamo_id', 'id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id', 'id');
    }

    public function usuarioRegistro()
    {
        return $this->belongsTo(User::class, 'usuario_registro', 'id');
    }

    public function usuarioConfirmo()
    {
        return $this->belongsTo(User::class, 'usuario_confirmo', 'id');
    }

    // Scopes útiles
    public function scopePendientes($query)
    {
        return $query->where('status', 0);
    }

    public function scopeConfirmados($query)
    {
        return $query->where('status', 1);
    }

    public function scopeRechazados($query)
    {
        return $query->where('status', 2);
    }

    // Accesores
    public function getStatusTextoAttribute()
    {
        $statuses = [
            0 => 'Pendiente',
            1 => 'Confirmado',
            2 => 'Rechazado'
        ];
        return $statuses[$this->status] ?? 'Desconocido';
    }

    public function getTipoPagoTextoAttribute()
    {
        $tipos = [
            'normal' => 'Normal',
            'anticipado' => 'Anticipado',
            'completo' => 'Completo'
        ];
        return $tipos[$this->tipo_pago] ?? $this->tipo_pago;
    }
}
