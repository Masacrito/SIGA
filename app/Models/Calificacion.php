<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Calificacion extends Model
{
    protected $table = 'calificaciones';

    protected $fillable = [
        'alumno_id',
        'materia_id',
        'periodo_id',
        'parcial',
        'calificacion',
        'tipo',
        'origen',
        'editado_por',
    ];

    protected function casts(): array
    {
        return [
            'parcial' => 'integer',
        ];
    }

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class, 'alumno_id');
    }

    public function materia(): BelongsTo
    {
        return $this->belongsTo(Materia::class, 'materia_id');
    }

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(PeriodoEscolar::class, 'periodo_id');
    }

    public function editadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'editado_por');
    }
}
