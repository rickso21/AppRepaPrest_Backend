<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ubicacion extends Model
{
    protected $table = 'tbl_ubicaciones';
    public $timestamps = false;

    protected $fillable = [
        'usuario_id', 'latitud', 'longitud',
        'precision_metros', 'velocidad', 'es_activa'
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
