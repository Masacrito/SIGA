<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Grupo extends Model
{
    protected $table = 'grupos';

    public $timestamps = false;

    protected $fillable = [
        'grado_id',
        'plantel_id',
        'periodo_id',
        'letra',
        'turno',
    ];

    public function grado(): BelongsTo
    {
        return $this->belongsTo(Grado::class, 'grado_id');
    }

    public function plantel(): BelongsTo
    {
        return $this->belongsTo(Plantel::class, 'plantel_id');
    }

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(PeriodoEscolar::class, 'periodo_id');
    }

    public function inscripciones(): HasMany
    {
        return $this->hasMany(Inscripcion::class, 'grupo_id');
    }

    public function alumnos(): BelongsToMany
    {
        return $this->belongsToMany(Alumno::class, 'inscripciones', 'grupo_id', 'alumno_id')
            ->withPivot(['periodo_id', 'situacion_academica', 'fecha_inscripcion']);
    }

    public function horarios(): HasMany
    {
        return $this->hasMany(Horario::class, 'grupo_id');
    }

    public function orientadores(): BelongsToMany
    {
        return $this->belongsToMany(Usuario::class, 'orientador_grupo', 'grupo_id', 'usuario_id');
    }
}
