<?php
// app/Models/EstadoRepartidor.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EstadoRepartidor extends Model
{
    protected $table = 'tbl_estado_repartidor';
    public $timestamps = false;

    protected $fillable = [
        'usuario_id', 'estado', 'mensaje_estado', 'ultima_actualizacion'
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
