<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class UsuarioApi extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'tbl_user';

    //tabla tbl_user que se pueden registrar o actualizar
    protected $fillable = [
        'nombre',
        'apellido_p',
        'apellido_m',
        'email',
        'telefono',
        'otp',
        'password',
        'rol_id',
        'status_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    // 4. Desactiva el comportamiento automático de timestamps si los manejas manualmente
    public $timestamps = false;
}
