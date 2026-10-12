<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Horario extends Model
{
    protected $table = 'horarios';

    public $timestamps = false;

    protected $fillable = [
        'grupo_id',
        'materia_id',
        'docente_id',
        'dia_semana',
        'hora_inicio',
        'hora_fin',
    ];

    protected function casts(): array
    {
        return [
            'dia_semana' => 'integer',
        ];
    }

    /** ¿La fecha (Y-m-d) cae en el día de la semana de este horario? 1=lunes ... 7=domingo */
    public function correspondeA(string $fecha): bool
    {
        return Carbon::parse($fecha)->dayOfWeekIso === $this->dia_semana;
    }

    /** IDs de los alumnos inscritos en el grupo de este horario, de entre los dados. */
    public function alumnosInscritos(array $alumnoIds): array
    {
        return Inscripcion::where('grupo_id', $this->grupo_id)->whereIn('alumno_id', $alumnoIds)->pluck('alumno_id')->all();
    }

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class, 'grupo_id');
    }

    public function materia(): BelongsTo
    {
        return $this->belongsTo(Materia::class, 'materia_id');
    }

    public function docente(): BelongsTo
    {
        return $this->belongsTo(Docente::class, 'docente_id');
    }

    public function sesiones(): HasMany
    {
        return $this->hasMany(SesionClase::class, 'horario_id');
    }
}
