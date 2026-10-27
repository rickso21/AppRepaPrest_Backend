<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoulettePrize extends Model
{
    protected $table = 'tbl_roulette_prizes';
    public $timestamps = true;

    protected $fillable = [
        'label', 'color', 'prize_type', 'reward_value',
        'weight', 'stock', 'is_active', 'valid_from', 'valid_until',
    ];

    protected $casts = [
        'is_active'    => 'boolean',
        'weight'       => 'integer',
        'stock'        => 'integer',
        'reward_value' => 'decimal:2',
        'valid_from'   => 'datetime',
        'valid_until'  => 'datetime',
    ];

    /** Premios elegibles ahora (activos, con stock, dentro de vigencia). */
    public static function eligible()
    {
        $now = now();

        return static::query()
            ->where('is_active', 1)
            ->where(fn($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', $now))
            ->where(fn($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', $now))
            ->where(fn($q) => $q->where('stock', -1)->orWhere('stock', '>', 0))
            ->orderBy('id')
            ->get();
    }
}
