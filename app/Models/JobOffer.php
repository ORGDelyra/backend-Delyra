<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobOffer extends Model
{
    //
    protected $fillable = [
        'titulo',
        'descripcion',
        'requisitos',
        'tipo_puesto',
        'salario',
        'estado',
        'fecha_publicacion',
        'branch_id',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }
}
