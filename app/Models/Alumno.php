<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
