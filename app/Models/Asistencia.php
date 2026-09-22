<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Asistencia extends Model
{
    protected $table = 'asistencias';

    protected $fillable = [
        'sesion_clase_id',
        'alumno_id',
        'registrado_por',
        'estado',
        'uuid_local',
        'capturada_en',
        'origen',
        'sincronizada_en',
    ];

    protected function casts(): array
    {
        return [
            'capturada_en' => 'datetime',
            'sincronizada_en' => 'datetime',
        ];
    }

    public function sesion(): BelongsTo
    {
        return $this->belongsTo(SesionClase::class, 'sesion_clase_id');
    }

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class, 'alumno_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'registrado_por');
    }

    public function justificacion(): HasOne
    {
        return $this->hasOne(Justificacion::class, 'asistencia_id');
    }
}
