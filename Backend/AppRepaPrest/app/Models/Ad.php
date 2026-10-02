<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ad extends Model
{
    protected $table = 'tbl_ads';

    protected $fillable = [
        'merchant_id', 'group_id', 'title', 'description', 'image', 'video',
        'link_url', 'phone', 'cta_text', 'priority', 'impressions', 'clicks',
        'start_at', 'end_at', 'status_id',
    ];

    protected $casts = [
        'start_at'    => 'datetime',
        'end_at'      => 'datetime',
        'impressions' => 'integer',
        'clicks'      => 'integer',
        'priority'    => 'integer',
    ];

    public function merchant()
    {
        return $this->belongsTo(User::class, 'merchant_id');
    }

    public function group()
    {
        return $this->belongsTo(Grupo::class, 'group_id');
    }

    public function getImageUrlAttribute()
    {
        return $this->image ? asset('img/ads/' . $this->image) : null;
    }

    public function getVideoUrlAttribute()
    {
        return $this->video ? asset('img/ads/' . $this->video) : null;
    }

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
}
