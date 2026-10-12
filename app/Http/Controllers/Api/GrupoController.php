<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Grupo;
use Illuminate\Http\JsonResponse;

class GrupoController extends Controller
{
    /** Lista para pasar asistencia. La pertenencia la revisa la ruta (can:ver-grupo). Incluye bajas con activo=false. */
    public function alumnos(Grupo $grupo): JsonResponse
    {
        return response()->json($grupo->alumnos()->orderBy('nombre')->get()->map(fn ($a) => [
            'id' => $a->id,
            'matricula' => $a->matricula,
            'nombre' => $a->nombre,
            'activo' => (bool) $a->activo,
            'situacion_academica' => $a->pivot->situacion_academica,
        ]));
    }
}
