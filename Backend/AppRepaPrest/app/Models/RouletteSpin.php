<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RouletteSpin extends Model
{
    protected $table = 'tbl_roulette_spins';
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'prize_id', 'prize_type', 'reward_value',
        'week_key', 'credited', 'credited_at', 'spin_at',
    ];

    protected $casts = [
        'credited'     => 'boolean',
        'credited_at'  => 'datetime',
        'spin_at'      => 'datetime',
        'reward_value' => 'decimal:2',
    ];

    public function prize()
    {
        return $this->belongsTo(RoulettePrize::class, 'prize_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
