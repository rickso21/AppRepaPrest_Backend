<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Support\Facades\Log;

#[Fillable(['nombre', 'apellido_p', 'apellido_m', 'email', 'password', 'token_pc', 'rol_id', 'status_id', 'telefono', 'grupo_id'])]
#[Hidden(['password', 'remember_token', 'token_pc'])]
#[Table('tbl_user')]


class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Boot the model and register event listeners.
     */
    protected static function booted(): void
    {
        static::created(function (User $user) {
            try {
                // 1. Crear configuración de pánico
                ConfiguracionPanico::create([
                    'usuario_id' => $user->id,
                    'recibir_alertas' => true,
                    'notificacion_push' => true,
                    'notificacion_whatsapp' => false,
                    'notificacion_correo' => false,
                    'radio_alerta_km' => 10.00,
                    'fecha_actualizacion' => now()
                ]);

                // 2. Crear estado del repartidor
                EstadoRepartidor::create([
                    'usuario_id' => $user->id,
                    'estado' => 'desconectado',
                    'mensaje_estado' => 'Usuario recién registrado',
                    'ultima_actualizacion' => now()
                ]);

                Log::info('Configuraciones creadas para usuario ID: ' . $user->id);

            } catch (\Exception $e) {
                Log::error('Error al crear configuraciones para usuario ID: ' . $user->id, [
                    'error' => $e->getMessage()
                ]);
            }
        });
    }

    public function configuracionPanico()
    {
        return $this->hasOne(ConfiguracionPanico::class, 'usuario_id');
    }

    public function estadoRepartidor()
    {
        return $this->hasOne(EstadoRepartidor::class, 'usuario_id');
    }

    public function alertasPanico()
    {
        return $this->hasMany(AlertaPanico::class, 'usuario_id');
    }

    // 📌 MÉTODOS DE ACCESO
    public function getEstadoActualAttribute()
    {
        $estado = $this->estadoRepartidor()->first();
        return $estado ? $estado->estado : 'desconectado';
    }

    public function getConfiguracionPanicoAttribute()
    {
        return $this->configuracionPanico()->first();
    }

    public function getUltimaAlertaAttribute()
    {
        return $this->alertasPanico()
            ->orderBy('fecha_activacion', 'desc')
            ->first();
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
