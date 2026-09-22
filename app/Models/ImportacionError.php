<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportacionError extends Model
{
    protected $table = 'importacion_errores';

    public $timestamps = false;

    protected $fillable = [
        'importacion_id',
        'fila',
        'columna',
        'mensaje',
    ];

    public function importacion(): BelongsTo
    {
        return $this->belongsTo(Importacion::class, 'importacion_id');
    }
}
