<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Configuracion extends Model
{
    protected $table = 'configuraciones';

    public $timestamps = false;

    protected $fillable = [
        'clave',
        'valor',
        'tipo_dato',
        'descripcion',
    ];

    /** Lee una configuración ya convertida a su tipo_dato. */
    public static function valor(string $clave, mixed $default = null): mixed
    {
        $conf = static::where('clave', $clave)->first();

        if (! $conf) {
            return $default;
        }

        return match ($conf->tipo_dato) {
            'entero' => (int) $conf->valor,
            'decimal' => (float) $conf->valor,
            'booleano' => filter_var($conf->valor, FILTER_VALIDATE_BOOLEAN),
            default => $conf->valor,
        };
    }
}
