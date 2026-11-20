<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Alumno extends Model
{
    use HasFactory;

    protected $table = 'alumnos';

    protected $fillable = [
        'matricula',
        'nombre',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    /**
     * Una inscripción por alumno y periodo (llave única): crea o actualiza la del
     * periodo activo con grupo_id / situacion_academica, si vienen en $datos.
     */
    public function inscribir(array $datos): void
    {
        $periodoId = PeriodoEscolar::activo()?->id;
        $cambios = array_intersect_key($datos, ['grupo_id' => true, 'situacion_academica' => true]);
        $inscripcion = Inscripcion::firstWhere(['alumno_id' => $this->id, 'periodo_id' => $periodoId]);

        if ($inscripcion) {
            $inscripcion->update($cambios);
        } elseif (isset($cambios['grupo_id'])) {
            Inscripcion::create($cambios + [
                'alumno_id' => $this->id,
                'periodo_id' => $periodoId,
                'situacion_academica' => 'regular',
                'fecha_inscripcion' => today(),
            ]);
        }
    }

    public function inscripciones(): HasMany
    {
        return $this->hasMany(Inscripcion::class, 'alumno_id');
    }

    public function padresTutores(): BelongsToMany
    {
        return $this->belongsToMany(PadreTutor::class, 'alumno_padre_tutor', 'alumno_id', 'padre_tutor_id')
            ->withPivot(['parentesco', 'es_principal']);
    }

    public function asistencias(): HasMany
    {
        return $this->hasMany(Asistencia::class, 'alumno_id');
    }

    public function calificaciones(): HasMany
    {
        return $this->hasMany(Calificacion::class, 'alumno_id');
    }

    public function observaciones(): HasMany
    {
        return $this->hasMany(Observacion::class, 'alumno_id');
    }

    public function notificaciones(): HasMany
    {
        return $this->hasMany(Notificacion::class, 'alumno_id');
    }
}
