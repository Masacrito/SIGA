<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SesionClase extends Model
{
    protected $table = 'sesiones_clase';

    public $timestamps = false;

    protected $fillable = [
        'horario_id',
        'fecha',
        'cancelada',
        'motivo_cancelacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'cancelada' => 'boolean',
        ];
    }

    /** Busca o crea la sesión de horario+fecha. whereDate y no firstOrCreate: el cast date guarda "Y-m-d 00:00:00" y en SQLite no empata con "Y-m-d". */
    public static function abrir(int $horarioId, string $fecha): self
    {
        return static::where('horario_id', $horarioId)->whereDate('fecha', $fecha)->first()
            ?? static::create(['horario_id' => $horarioId, 'fecha' => $fecha]);
    }

    public function horario(): BelongsTo
    {
        return $this->belongsTo(Horario::class, 'horario_id');
    }

    public function asistencias(): HasMany
    {
        return $this->hasMany(Asistencia::class, 'sesion_clase_id');
    }
}
