<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Subscription extends Model
{
    protected $table = 'tbl_subscriptions';

    protected $fillable = [
        'user_id', 'plan', 'status', 'price_mxn',
        'coupon_code', 'payment_id', 'preference_id',
        'starts_at', 'expires_at',
    ];

    protected $casts = [
        'starts_at'  => 'datetime',
        'expires_at' => 'datetime',
        'price_mxn'  => 'decimal:2',
    ];

    // ============================================================
    // Helpers de negocio (SQL directo para máximo rendimiento)
    // ============================================================

    /**
     * Devuelve la suscripción activa más reciente del usuario, o null.
     */
    public static function activeForUser(int $userId): ?self
    {
        $row = DB::selectOne(
            'SELECT * FROM tbl_subscriptions
             WHERE user_id = ?
               AND status = ?
               AND expires_at > NOW()
             ORDER BY expires_at DESC
             LIMIT 1',
            [$userId, 'active']
        );

        if (!$row) return null;

        $sub = new self();
        $sub->forceFill((array) $row);
        $sub->exists = true;
        return $sub;
    }

    /**
     * Devuelve el estado resumido para el frontend.
     */
    public static function statusPayloadForUser(int $userId): array
    {
        $active = self::activeForUser($userId);

        if ($active) {
            return [
                'status'     => 'active',
                'plan'       => $active->plan,
                'expires_at' => $active->expires_at?->format('Y-m-d H:i'),
                'is_active'  => true,
                'coupon'     => $active->coupon_code,
            ];
        }

        // Buscar la última suscripción (histórica o expirada)
        $last = DB::selectOne(
            'SELECT * FROM tbl_subscriptions
             WHERE user_id = ?
             ORDER BY expires_at DESC
             LIMIT 1',
            [$userId]
        );

        return [
            'status'     => $last?->status ?? 'none',
            'plan'       => $last?->plan ?? null,
            'expires_at' => $last?->expires_at,
            'is_active'  => false,
            'coupon'     => $last?->coupon_code ?? null,
        ];
    }
}
