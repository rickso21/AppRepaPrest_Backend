<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlertaPanico extends Model
{
    protected $table = 'tbl_alertas_panico';
    public $timestamps = false;

    protected $fillable = [
        'usuario_id', 'latitud', 'longitud', 'tipo_emergencia',
        'descripcion_adicional', 'estado', 'fecha_activacion',
        'fecha_desactivacion', 'desactivada_por_usuario_id',
        'razon_desactivacion', 'activo'
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
