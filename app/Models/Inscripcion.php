<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inscripcion extends Model
{
    protected $table = 'inscripciones';

    public $timestamps = false;

    protected $fillable = [
        'alumno_id',
        'grupo_id',
        'periodo_id',
        'situacion_academica',
        'fecha_inscripcion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inscripcion' => 'date',
        ];
    }

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class, 'alumno_id');
    }

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class, 'grupo_id');
    }

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(PeriodoEscolar::class, 'periodo_id');
    }
}
