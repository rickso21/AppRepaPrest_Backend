<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Grupo extends Model
{
    protected $table = 'tbl_group';
    public $timestamps = false;

    protected $fillable = [
        'group_name', 'code', 'user_leader_id', 'status'
    ];

    // public function usuario()
    // {
    //     return $this->belongsTo(User::class, 'user_leader_id');
    // }
}
