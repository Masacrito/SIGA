<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PeriodoEscolar extends Model
{
    protected $table = 'periodos_escolares';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'fecha_inicio',
        'fecha_fin',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
        ];
    }

    /** El periodo con estado 'activo' (solo debe haber uno a la vez). */
    public static function activo(): ?self
    {
        return static::where('estado', 'activo')->first();
    }

    public function grupos(): HasMany
    {
        return $this->hasMany(Grupo::class, 'periodo_id');
    }
}
