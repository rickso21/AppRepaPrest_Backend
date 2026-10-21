<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ad extends Model
{
    protected $table = 'tbl_ads';

    protected $fillable = [
        // Datos base
        'merchant_id', 'group_id', 'title', 'description', 'image', 'video',
        'link_url', 'phone', 'cta_text', 'priority', 'impressions', 'clicks',
        'start_at', 'end_at', 'status_id',

        // Pago
        'payment_status', 'payment_status_detail',
        'preference_id', 'payment_id', 'payment_method',
        'amount_paid', 'plan_id',
        'paid_at', 'payment_notified_at', 'payment_payload',
    ];

    protected $casts = [
        // Fechas de vigencia
        'start_at' => 'datetime',
        'end_at'   => 'datetime',

        // Métricas
        'impressions' => 'integer',
        'clicks'      => 'integer',
        'priority'    => 'integer',

        // Pago
        'plan_id'              => 'integer',
        'amount_paid'          => 'decimal:2',
        'paid_at'              => 'datetime',
        'payment_notified_at'  => 'datetime',
        'payment_payload'      => 'array', // JSON <-> array automático
    ];

    // ================================================================
    // RELACIONES
    // ================================================================

    public function merchant()
    {
        return $this->belongsTo(User::class, 'merchant_id');
    }

    public function group()
    {
        return $this->belongsTo(Grupo::class, 'group_id');
    }

    // ================================================================
    // ACCESSORS
    // ================================================================

    public function getImageUrlAttribute()
    {
        return $this->image ? asset('img/ads/' . $this->image) : null;
    }

    public function getVideoUrlAttribute()
    {
        return $this->video ? asset('img/ads/' . $this->video) : null;
    }

    // ================================================================
    // SCOPES
    // ================================================================

    /**
     * Anuncios activos (aprobados y dentro de vigencia).
     */
    public function scopeActive($query)
    {
        return $query->where('status_id', 1)
            ->where(function ($q) {
                $q->whereNull('start_at')->orWhere('start_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('end_at')->orWhere('end_at', '>=', now());
            });
    }

    /**
     * Anuncios con pago aprobado.
     */
    public function scopePaid($query)
    {
        return $query->where('payment_status', 'approved');
    }

    /**
     * Anuncios con pago pendiente (preferencia creada, esperando confirmación).
     */
    public function scopePendingPayment($query)
    {
        return $query->where('payment_status', 'pending');
    }

    /**
     * Anuncios con pago rechazado o cancelado.
     */
    public function scopeFailedPayment($query)
    {
        return $query->whereIn('payment_status', ['rejected', 'cancelled']);
    }

    /**
     * Anuncios sin preferencia creada todavía.
     */
    public function scopeWithoutPayment($query)
    {
        return $query->whereNull('payment_status');
    }

    // ================================================================
    // HELPERS DE ESTADO
    // ================================================================

    public function isPaid(): bool
    {
        return $this->payment_status === 'approved';
    }

    public function isPaymentPending(): bool
    {
        return in_array($this->payment_status, ['pending', 'in_process'], true);
    }

    public function isPaymentFailed(): bool
    {
        return in_array($this->payment_status, ['rejected', 'cancelled'], true);
    }

    public function isActiveNow(): bool
    {
        if ((int) $this->status_id !== 1) {
            return false;
        }

        $now = now();

        if ($this->start_at && $this->start_at->isFuture()) {
            return false;
        }

        if ($this->end_at && $this->end_at->isPast()) {
            return false;
        }

        return true;
    }
}
