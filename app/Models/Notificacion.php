<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notificacion extends Model
{
    protected $table = 'notificaciones';

    protected $fillable = [
        'alumno_id',
        'padre_tutor_id',
        'tipo',
        'canal',
        'estado',
        'mensaje',
        'fecha_envio',
        'error_detalle',
    ];

    protected function casts(): array
    {
        return [
            'fecha_envio' => 'datetime',
        ];
    }

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class, 'alumno_id');
    }

    public function padreTutor(): BelongsTo
    {
        return $this->belongsTo(PadreTutor::class, 'padre_tutor_id');
    }
}
