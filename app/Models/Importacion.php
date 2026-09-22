<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Importacion extends Model
{
    protected $table = 'importaciones';

    /** Solo tiene created_at. */
    const UPDATED_AT = null;

    protected $fillable = [
        'usuario_id',
        'periodo_id',
        'tipo',
        'nombre_archivo',
        'filas_procesadas',
        'filas_error',
        'estado',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(PeriodoEscolar::class, 'periodo_id');
    }

    public function errores(): HasMany
    {
        return $this->hasMany(ImportacionError::class, 'importacion_id');
    }
}
