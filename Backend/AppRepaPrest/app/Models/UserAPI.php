<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class UserAPI extends Model
{
    use HasApiTokens, HasFactory, Notifiable;
    protected $table = "tbl_users_api";
    protected $primaryKey = "id_user";
    protected $hidden = ["created_at", "updated_at"];
    protected $fillable = [
        'user',
        'password'
    ];
}
