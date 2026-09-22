<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SesionClase extends Model
{
    protected $table = 'sesiones_clase';

    public $timestamps = false;

    protected $fillable = [
        'horario_id',
        'fecha',
        'cancelada',
        'motivo_cancelacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'cancelada' => 'boolean',
        ];
    }

    public function horario(): BelongsTo
    {
        return $this->belongsTo(Horario::class, 'horario_id');
    }

    public function asistencias(): HasMany
    {
        return $this->hasMany(Asistencia::class, 'sesion_clase_id');
    }
}
