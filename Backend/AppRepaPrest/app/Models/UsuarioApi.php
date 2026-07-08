<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\HasApiTokens;

class UsuarioApi extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'usuario_api';

    protected $hidden = [
        'id',
        'remember_token',
    ];

    protected $fillable = [
        'user',
        'password',
    ];

      public $timestamps = false;
}
