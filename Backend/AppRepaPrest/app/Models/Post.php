<?php
namespace App\Models;

use App\Models\Comment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    protected $table = 'tbl_post';

    protected $fillable = [
        'post', 'image', 'user_id', 'group_id', 'activo'
    ];

    public function user()
    {
        return $this->belongsTo(User::class,'user_id');
    }
    public function comments()
    {
        return $this->hasMany(Comment::class, 'post_id');
    }
}
