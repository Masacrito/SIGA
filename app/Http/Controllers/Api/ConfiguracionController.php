<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Configuracion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** CU-19: parámetros generales (clave → valor). */
class ConfiguracionController extends Controller
{
    /** Reglas extra de claves conocidas; el resto solo se valida por su tipo_dato. */
    private const REGLAS = [
        'escala_calificacion' => ['in:10,100'],
        'zona_horaria' => ['timezone:all'],
        'retardos_equivalen_falta' => ['min:0', 'max:20'],
    ];

    public function index(): JsonResponse
    {
        return response()->json(Configuracion::orderBy('clave')->get(['clave', 'valor', 'tipo_dato', 'descripcion']));
    }

    /** Solo edita claves existentes: crearlas es cosa de seeders/migraciones. */
    public function update(Request $request): JsonResponse
    {
        $request->validate(['clave' => ['required', 'string', 'exists:configuraciones,clave']]);
        $conf = Configuracion::where('clave', $request->input('clave'))->first();

        $tipo = match ($conf->tipo_dato) {
            'entero' => ['integer'],
            'decimal' => ['numeric'],
            'booleano' => ['in:0,1,true,false'],
            default => ['string', 'max:255'],
        };
        $datos = $request->validate(['valor' => ['required', ...$tipo, ...(self::REGLAS[$conf->clave] ?? [])]]);

        $conf->update(['valor' => (string) $datos['valor']]);

        return response()->json($conf->only(['clave', 'valor', 'tipo_dato', 'descripcion']));
    }
}
