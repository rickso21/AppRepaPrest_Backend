<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\LineaCredito;
use App\Models\Prestamo;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['nombre', 'apellido_p', 'apellido_m', 'email', 'password', 'token_pc', 'rol_id', 'status_id', 'telefono', 'grupo_id'])]
#[Hidden(['password', 'remember_token', 'token_pc'])]
#[Table('tbl_user')]

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;


    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function linea_credito()
    {
        return $this->hasMany(LineaCredito::class, 'usuario_id', 'id');
    }

    public function prestamos()
    {
        return $this->hasMany(Prestamo::class, 'usuario_id', 'id');
    }
}
