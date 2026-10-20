<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ubicacion extends Model
{
    protected $table = 'tbl_ubicaciones';
    public $timestamps = false;

    // FIX: 'created_at' faltaba en $fillable, por lo que updateOrCreate() lo ignoraba
    // y "ultima_ubicacion" nunca se actualizaba.
    protected $fillable = [
        'usuario_id', 'latitud', 'longitud',
        'precision_metros', 'velocidad', 'es_activa', 'created_at',
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
