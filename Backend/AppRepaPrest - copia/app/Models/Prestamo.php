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
    'monto_restante',          // ← NUEVO
    'numero_pagos',
    'pagos_realizados',        // ← NUEVO
    'quincenas_completadas',   // 🔑 NUEVO
    'periodicidad',
    'fecha_solicitud',
    'fecha_aprobacion',
    'fecha_desembolso',
    'fecha_primer_pago',
    'fecha_ultimo_pago',       // ← NUEVO
    'fecha_fin',               // 🔑 por si lo usas al liquidar
    'fecha_liquidacion',       // 🔑 por si lo usas al liquidar
    'estado_prestamo_id',
    'incremento_aplicado',
    'fecha_incremento',
    'ruta_pdf_aprobacion',
    'ruta_pdf_liquidacion',
    'ruta_pdf_rechazo',
])]
#[Hidden(['id'])]
#[Table('tbl_prestamo')]
class Prestamo extends Model
{
    public $timestamps = false;

    /**
     * 🔑 Atributos calculados que se incluyen al serializar el modelo a JSON.
     */
    protected $appends = [
        'quincenas_restantes',
        'quincenas_completadas_calculadas',
    ];

    // ============================================
    // RELACIONES
    // ============================================

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id', 'id');
    }

    public function lineaCredito()
    {
        return $this->belongsTo(LineaCredito::class, 'linea_credito_id', 'id');
    }

    public function estado()
    {
        return $this->belongsTo(EstadoPrestamo::class, 'estado_prestamo_id', 'id');
    }

    public function pagos()
    {
        return $this->hasMany(Pago::class, 'prestamo_id', 'id');
    }

    // ============================================
    // ACCESSORS
    // ============================================

    /**
     * 🔑 Quincenas restantes calculadas por monto.
     *
     * Reglas:
     * - Si ya no hay saldo (monto_restante <= 0) → 0 quincenas
     * - Si aún hay saldo → al menos 1 quincena restante
     *
     * Este accessor se expone como `quincenas_restantes` en el JSON.
     */
    public function getQuincenasRestantesAttribute(): int
    {
        // Si ya no hay saldo, 0 quincenas
        if (($this->monto_restante ?? 0) <= 0) {
            return 0;
        }

        // Total de quincenas del préstamo
        $numero_pagos = (int) ($this->numero_pagos ?? 0);
        if ($numero_pagos <= 0) {
            return 1;
        }

        // Quincenas completadas (usa el campo si existe, si no calcula por monto)
        $completadas = $this->quincenas_completadas_calculadas;

        // Base: total menos completadas
        $base = max(0, $numero_pagos - $completadas);

        // 🔑 Si aún hay saldo, mínimo 1 quincena por pagar
        return max(1, $base);
    }

    /**
     * 🔑 Quincenas completadas calculadas por monto.
     *
     * - Si el campo `quincenas_completadas` existe y es válido, se usa.
     * - Si no, se calcula por monto acumulado pagado.
     *
     * Se expone como `quincenas_completadas_calculadas` en el JSON.
     */
    public function getQuincenasCompletadasCalculadasAttribute(): int
    {
        $numero_pagos = (int) ($this->numero_pagos ?? 0);

        if ($numero_pagos <= 0) {
            return 0;
        }

        // Si ya está liquidado, todas las quincenas están completas
        if (($this->monto_restante ?? 0) <= 0) {
            return $numero_pagos;
        }

        // Si el campo existe y tiene valor, usarlo como fuente principal
        // (pero nunca mayor al total)
        if (isset($this->attributes['quincenas_completadas'])) {
            $completadas = (int) $this->attributes['quincenas_completadas'];

            // Tope
            $completadas = min($completadas, $numero_pagos);

            // 🔑 Si aún hay saldo y todas están completas por monto, dejar al menos 1 pendiente
            if ($completadas >= $numero_pagos && ($this->monto_restante ?? 0) > 0) {
                $completadas = max(0, $numero_pagos - 1);
            }

            return $completadas;
        }

        // Fallback: calcular por monto acumulado
        $monto_total = (float) ($this->monto_total_pagar ?? 0);
        if ($monto_total <= 0) {
            return 0;
        }

        $pago_quincenal = $monto_total / $numero_pagos;
        if ($pago_quincenal <= 0) {
            return 0;
        }

        $monto_pagado = $monto_total - ($this->monto_restante ?? $monto_total);
        $monto_pagado = floor($monto_pagado * 100) / 100; // evitar problemas de float

        $completadas = (int) floor($monto_pagado / $pago_quincenal);

        // Tope
        $completadas = min($completadas, $numero_pagos);

        // 🔑 Misma regla: si hay saldo, dejar al menos 1 pendiente
        if ($completadas >= $numero_pagos && ($this->monto_restante ?? 0) > 0) {
            $completadas = max(0, $numero_pagos - 1);
        }

        return $completadas;
    }

    /**
     * 🔑 Porcentaje pagado, blindado:
     * - Nunca 100 si aún hay saldo pendiente
     * - 100 si ya está liquidado
     *
     * Opcional: si ya usas `calcularPorcentajePagado()` en otro lado,
     * puedes omitir este accessor y usar la función global.
     */
    public function getPorcentajePagadoAttribute(): float
    {
        $monto_total = (float) ($this->monto_total_pagar ?? 0);

        if ($monto_total <= 0) {
            return 0.0;
        }

        if (($this->monto_restante ?? 0) <= 0) {
            return 100.0;
        }

        $monto_pagado = $monto_total - ($this->monto_restante ?? $monto_total);

        // Redondear hacia abajo con 2 decimales
        $porcentaje = floor(($monto_pagado / $monto_total) * 10000) / 100;

        // Tope duro: nunca 100 si hay saldo
        $porcentaje = min($porcentaje, 99.99);

        return round($porcentaje, 2);
    }
}
