<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Grupo extends Model
{
    protected $table = 'tbl_group';
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'int';

    public const TIPO_PRINCIPAL  = 1;
    public const TIPO_EMERGENCIA = 2;
    public const TIPO_MONITOREO  = 3;

    protected $fillable = [
    'id',
    'group_name',
    'code',
    'user_leader_id',
    'status',
    'parent_id',
    'tipo_grupo',
];


    protected $casts = [
        'parent_id'  => 'integer',
        'tipo_grupo' => 'integer',
        'status'     => 'integer',
    ];


    public function padre()
    {
        return $this->belongsTo(Grupo::class, 'parent_id');
    }

    public function subgrupos()
    {
        return $this->hasMany(Grupo::class, 'parent_id');
    }

    public function emergencia()
    {
        return $this->hasOne(Grupo::class, 'parent_id')
            ->where('tipo_grupo', self::TIPO_EMERGENCIA);
    }

    public function monitoreo()
    {
        return $this->hasOne(Grupo::class, 'parent_id')
            ->where('tipo_grupo', self::TIPO_MONITOREO);
    }

    public function lider()
    {
        return $this->belongsTo(User::class, 'user_leader_id');
    }

    public function usuarios()
    {
        return $this->hasMany(User::class, 'grupo_id');
    }

    // ---- helpers ----

    public function esPrincipal(): bool
    {
        return $this->tipo_grupo === self::TIPO_PRINCIPAL;
    }

    public function esEmergencia(): bool
    {
        return $this->tipo_grupo === self::TIPO_EMERGENCIA;
    }

    public function esMonitoreo(): bool
    {
        return $this->tipo_grupo === self::TIPO_MONITOREO;
    }

    /**
     * Devuelve el ID del grupo principal dado cualquier grupo.
     * Si no existe, devuelve el mismo ID.
     */
    public static function canalPrincipalDe(?int $groupId): int
    {
        if (!$groupId) return 0;

        $grupo = self::find($groupId);

        return $grupo?->parent_id ?? (int) $groupId;
    }

    /**
     * Devuelve los IDs de grupos visibles para un usuario:
     * principal + emergencias + monitoreo.
     */
    public static function gruposVisiblesDe(int $grupoPrincipalId): array
    {
        $subgrupos = self::where('parent_id', $grupoPrincipalId)
            ->pluck('id')
            ->all();

        return array_merge([$grupoPrincipalId], $subgrupos);
    }

    // public function usuario()
    // {
    //     return $this->belongsTo(User::class, 'user_leader_id');
    // }

    /**
 * Genera un ID aleatorio único para un nuevo grupo.
 * Rango: 10000 - 99999 (5 dígitos).
 */
public static function generarIdUnico(int $min = 10000, int $max = 99999, int $intentos = 50): int
{
    for ($i = 0; $i < $intentos; $i++) {
        $id = random_int($min, $max);

        if (!self::where('id', $id)->exists()) {
            return $id;
        }
    }

    throw new \RuntimeException('No se pudo generar un ID único para el grupo.');
}

    }
