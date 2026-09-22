<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DispositivoPadre extends Model
{
    protected $table = 'dispositivos_padres';

    protected $fillable = [
        'padre_tutor_id',
        'token_fcm',
        'plataforma',
        'activo',
        'ultimo_uso',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'ultimo_uso' => 'datetime',
        ];
    }

    public function padreTutor(): BelongsTo
    {
        return $this->belongsTo(PadreTutor::class, 'padre_tutor_id');
    }
}
