<?php

namespace App\Models;

use Database\Factories\UsuarioFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Usuario extends Authenticatable
{
    /** @use HasFactory<UsuarioFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'usuarios';

    protected $fillable = [
        'rol_id',
        'nombre',
        'username',
        'password',
        'correo',
        'activo',
    ];

    /** es_super_admin no es asignable en masa: solo lo pone SuperAdminSeeder. */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'es_super_admin' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'rol_id');
    }

    public function docente(): HasOne
    {
        return $this->hasOne(Docente::class, 'usuario_id');
    }

    /** Grupos asignados a un orientador (CU-18). */
    public function gruposOrientados(): BelongsToMany
    {
        return $this->belongsToMany(Grupo::class, 'orientador_grupo', 'usuario_id', 'grupo_id');
    }

    public function dispositivos(): HasMany
    {
        return $this->hasMany(Dispositivo::class, 'usuario_id');
    }

    /** Slugs de permiso efectivos del usuario (vacío = ninguno). */
    public function permisos(): array
    {
        return $this->rol?->permisos->pluck('nombre')->all() ?? [];
    }

    /** El súper admin pasa cualquier verificación de permiso. */
    public function tienePermiso(string $permiso): bool
    {
        return $this->es_super_admin || in_array($permiso, $this->permisos(), true);
    }
}
