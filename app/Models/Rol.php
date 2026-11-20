<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rol extends Model
{
    /** Roles que dan clase: tienen fila en docentes y pueden estar en un horario. */
    const FRENTE_A_GRUPO = ['docente', 'orientador'];

    protected $table = 'roles';

    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = [
        'nombre',
        'descripcion',
    ];

    public function permisos(): BelongsToMany
    {
        return $this->belongsToMany(Permiso::class, 'rol_permiso', 'rol_id', 'permiso_id');
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class, 'rol_id');
    }
}
