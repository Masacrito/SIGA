<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UmbralRiesgo extends Model
{
    protected $table = 'umbrales_riesgo';

    public $timestamps = false;

    protected $fillable = [
        'periodo_id',
        'nivel',
        'porcentaje_min',
        'porcentaje_max',
        'faltas_consecutivas',
        'color_hex',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'porcentaje_min' => 'decimal:2',
            'porcentaje_max' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(PeriodoEscolar::class, 'periodo_id');
    }
}
