<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Justificacion extends Model
{
    protected $table = 'justificaciones';

    public $timestamps = false;

    protected $fillable = [
        'asistencia_id',
        'motivo',
        'evidencia_url',
        'autorizada_por',
        'fecha_autorizacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_autorizacion' => 'datetime',
        ];
    }

    public function asistencia(): BelongsTo
    {
        return $this->belongsTo(Asistencia::class, 'asistencia_id');
    }

    public function autorizadaPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'autorizada_por');
    }
}
