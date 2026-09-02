<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    protected $table = 'tbl_comments';

    protected $fillable = [
        'comment', 'post_id', 'user_id', 'activo'
    ];

    public function user()
    {
        return $this->belongsTo(User::class,'user_id');
    }

}
