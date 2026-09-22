<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Observacion extends Model
{
    protected $table = 'observaciones';

    protected $fillable = [
        'alumno_id',
        'orientador_id',
        'fecha',
        'texto',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
        ];
    }

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class, 'alumno_id');
    }

    public function orientador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'orientador_id');
    }
}
