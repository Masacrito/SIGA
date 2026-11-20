<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PadreTutor extends Model
{
    use HasFactory;

    protected $table = 'padres_tutores';

    protected $fillable = [
        'nombre',
        'telefono',
        'correo',
        'canal_preferido',
    ];

    /** Sin push en esta fase (2026-09-18): el canal debe tener a dónde llegar. También las usa la importación. */
    public static function reglas(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:150'],
            'canal_preferido' => ['required', 'in:sms,email'],
            'telefono' => ['nullable', 'string', 'regex:/^[0-9 +()-]{7,20}$/', 'required_if:canal_preferido,sms'],
            'correo' => ['nullable', 'email', 'max:160', 'required_if:canal_preferido,email'],
        ];
    }

    public function alumnos(): BelongsToMany
    {
        return $this->belongsToMany(Alumno::class, 'alumno_padre_tutor', 'padre_tutor_id', 'alumno_id')
            ->withPivot(['parentesco', 'es_principal']);
    }

    /** Sin uso en esta fase: no hay push a padres (decisión 2026-09-18). */
    public function dispositivos(): HasMany
    {
        return $this->hasMany(DispositivoPadre::class, 'padre_tutor_id');
    }
}
