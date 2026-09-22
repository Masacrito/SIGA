<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Docente extends Model
{
    protected $table = 'docentes';

    public $timestamps = false;

    protected $fillable = [
        'usuario_id',
        'numero_empleado',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function horarios(): HasMany
    {
        return $this->hasMany(Horario::class, 'docente_id');
    }
}
