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

#[Fillable([
    'nombre',
    'apellido_p',
    'apellido_m',
    'email',
    'password',
    'token_pc',
    'rol_id',
    'status_id',
    'telefono',
    'ciudad',
    'avatar',
     'nombre_comercio',
    'portada',
    'grupo_id',
    'en_llamada',
    'llamada_de',
    'current_channel'
])]
#[Hidden(['password', 'remember_token', 'token_pc'])]
#[Table('tbl_user')]

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    // ========== CONFIGURACIÓN DE CASTS ==========
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'en_llamada' => 'boolean',
        ];
    }

    // ========== BOOT - CREACIÓN DE CONFIGURACIONES ==========
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

    // ========== RELACIONES ==========

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

    /**
     * Obtener la alerta de pánico activa del usuario
     */
    public function alertaPanicoActiva()
    {
        return $this->hasOne(AlertaPanico::class, 'usuario_id')
            ->where('estado', 'activa');
    }

    public function linea_credito()
    {
        return $this->hasMany(LineaCredito::class, 'usuario_id', 'id');
    }

    public function prestamos()
    {
        return $this->hasMany(Prestamo::class, 'usuario_id', 'id');
    }

    // ========== ACCESORES (GETTERS) ==========

    // Estado actual del repartidor
    public function getEstadoActualAttribute()
    {
        $estado = $this->estadoRepartidor()->first();
        return $estado ? $estado->estado : 'desconectado';
    }

    // Configuración de pánico
    public function getConfiguracionPanicoAttribute()
    {
        return $this->configuracionPanico()->first();
    }

    // Última alerta de pánico
    public function getUltimaAlertaAttribute()
    {
        return $this->alertasPanico()
            ->orderBy('fecha_activacion', 'desc')
            ->first();
    }

    // ========== NUEVO PARA VOZ ==========

    /**
     * Obtener el nombre completo del usuario
     */
    public function getFullNameAttribute()
    {
        return trim($this->nombre . ' ' . $this->apellido_p . ' ' . $this->apellido_m);
    }

    /**
     * Verificar si el usuario está en pánico (usando la tabla de alertas)
     */
    public function getEstaEnPanicoAttribute()
    {
        return $this->alertaPanicoActiva()->exists();
    }

    /**
     * Verificar si el usuario está en llamada
     */
    public function getEstaEnLlamadaAttribute()
    {
        return (bool) $this->en_llamada;
    }

    /**
     * Verificar si el usuario está conectado (usando EstadoRepartidor)
     */
    public function getEstaConectadoAttribute()
    {
        $estado = $this->estadoRepartidor()->first();
        return $estado && $estado->estado === 'conectado';
    }

    /**
     * Obtener el estado de conexión desde EstadoRepartidor
     */
    public function getEstadoConexionAttribute()
    {
        $estado = $this->estadoRepartidor()->first();
        return $estado ? $estado->estado : 'desconectado';
    }

    // ========== MUTADORES (SETTERS) ==========

    /**
     * Activar pánico - Crear alerta en la tabla dedicada
     */
    public function activarPanico($latitud, $longitud, $tipoEmergencia, $descripcion = null)
    {
        // Desactivar alertas anteriores
        AlertaPanico::where('usuario_id', $this->id)
            ->where('estado', 'activa')
            ->update(['estado' => 'desactivada']);

        // Crear nueva alerta
        return AlertaPanico::create([
            'usuario_id' => $this->id,
            'latitud' => $latitud,
            'longitud' => $longitud,
            'tipo_emergencia' => $tipoEmergencia,
            'descripcion_adicional' => $descripcion,
            'estado' => 'activa'
        ]);
    }

    /**
     * Desactivar pánico
     */
    public function desactivarPanico()
    {
        $alerta = $this->alertaPanicoActiva()->first();
        if ($alerta) {
            $alerta->update(['estado' => 'desactivada']);
        }
        return $alerta;
    }

    /**
     * Conectar usuario - Actualizar EstadoRepartidor
     */
    public function conectar()
    {
        EstadoRepartidor::updateOrCreate(
            ['usuario_id' => $this->id],
            [
                'estado' => 'conectado',
                'ultima_actualizacion' => now()
            ]
        );
    }

    /**
     * Desconectar usuario - Actualizar EstadoRepartidor
     */
    public function desconectar()
    {
        EstadoRepartidor::updateOrCreate(
            ['usuario_id' => $this->id],
            [
                'estado' => 'desconectado',
                'ultima_actualizacion' => now()
            ]
        );

        $this->update([
            'en_llamada' => false,
            'llamada_de' => null
        ]);
    }

    /**
     * Iniciar llamada
     */
    public function iniciarLlamada($userId)
    {
        $this->update([
            'en_llamada' => true,
            'llamada_de' => $userId
        ]);
    }

    /**
     * Finalizar llamada
     */
    public function finalizarLlamada()
    {
        $this->update([
            'en_llamada' => false,
            'llamada_de' => null
        ]);
    }

    /**
     * 🔥 Obtener usuarios disponibles para llamada (excluyendo al actual)
     */
    public static function getAvailableUsers($excludeUserId)
    {
        return self::where('id', '!=', $excludeUserId)
            ->whereHas('estadoRepartidor', function ($query) {
                $query->where('estado', 'conectado');
            })
            ->whereDoesntHave('alertaPanicoActiva')
            ->where('en_llamada', false)
            ->select('id', 'nombre', 'apellido_p', 'apellido_m', 'rol_id', 'telefono')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->full_name,
                    'rol' => $user->rol_id,
                    'telefono' => $user->telefono,
                    'en_panico' => $user->esta_en_panico,
                    'en_llamada' => $user->en_llamada,
                    'estado_conexion' => $user->estado_conexion,
                ];
            });
    }

    public function grupo()
    {
        return $this->belongsTo(Grupo::class, 'grupo_id', 'id');
    }

    // Accessors para devolver URL completa
    protected $appends = ['avatar_url', 'portada_url'];

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar ? asset('storage/' . $this->avatar) : null;
    }

    public function getPortadaUrlAttribute(): ?string
    {
        return $this->portada ? asset('storage/' . $this->portada) : null;
    }

    public function getLogoUrlAttribute()
{
    return $this->logo ? asset('storage/' . $this->logo) : null;
}
}
