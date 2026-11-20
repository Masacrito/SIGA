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

    /**
     * Numérica: 0 a la escala activa (10 o 100), un decimal como máximo; se normaliza
     * ("08.0" → "8"). Alfanumérica: A o NA. Si no es válida lanza InvalidArgumentException
     * con el mensaje para el usuario. La usan la corrección manual y la importación.
     */
    public static function normalizar(string $valor, string $tipoEvaluacion): string
    {
        $valor = strtoupper(trim($valor));
        if ($tipoEvaluacion === 'alfanumerica') {
            if (! in_array($valor, ['A', 'NA'], true)) {
                throw new \InvalidArgumentException('Esta materia se califica con A (acreditada) o NA (no acreditada).');
            }

            return $valor;
        }

        $escala = (int) Configuracion::valor('escala_calificacion', 10);
        if (! preg_match('/^\d{1,3}(\.\d)?$/', $valor) || (float) $valor > $escala) {
            throw new \InvalidArgumentException("La calificación debe ser un número de 0 a {$escala}, con un decimal como máximo.");
        }

        return (string) (float) $valor; // "8.50" no pasa la regex
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
