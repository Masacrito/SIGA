<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Materia extends Model
{
    protected $table = 'materias';

    public $timestamps = false;

    protected $fillable = [
        'clave',
        'nombre',
        'tipo_evaluacion',
    ];

    public function horarios(): HasMany
    {
        return $this->hasMany(Horario::class, 'materia_id');
    }
}
