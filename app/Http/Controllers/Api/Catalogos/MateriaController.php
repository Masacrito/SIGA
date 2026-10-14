<?php

namespace App\Http\Controllers\Api\Catalogos;

use App\Http\Controllers\Controller;
use App\Models\Materia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** CU-11 */
class MateriaController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Materia::orderBy('nombre')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'clave' => ['required', 'string', 'max:10', 'unique:materias,clave'],
            'nombre' => ['required', 'string', 'max:200'],
            'tipo_evaluacion' => ['sometimes', 'in:numerica,alfanumerica'],
        ]);

        return response()->json(Materia::create($datos + ['tipo_evaluacion' => 'numerica']), 201);
    }
}
