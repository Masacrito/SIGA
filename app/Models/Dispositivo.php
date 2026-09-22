<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Dispositivo extends Model
{
    protected $table = 'dispositivos';

    protected $fillable = [
        'usuario_id',
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

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
