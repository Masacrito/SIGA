<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Grupo;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** CU-18: qué grupos sigue cada orientador (la pertenencia del semáforo sale de aquí). */
class OrientadorGrupoController extends Controller
{
    public function index(Usuario $usuario): JsonResponse
    {
        $grupos = $usuario->gruposOrientados()->with('grado')->get();

        return response()->json($grupos->map->aApi());
    }

    public function store(Request $request, Usuario $usuario): JsonResponse
    {
        abort_if($usuario->rol?->nombre !== 'orientador', 422, 'El usuario no tiene el rol orientador.');
        $datos = $request->validate(['grupo_id' => ['required', 'integer', 'exists:grupos,id']]);
        abort_if($usuario->gruposOrientados()->whereKey($datos['grupo_id'])->exists(), 409, 'El grupo ya está asignado a este orientador.');

        $usuario->gruposOrientados()->attach($datos['grupo_id']);

        return response()->json(Grupo::with('grado')->find($datos['grupo_id'])->aApi(), 201);
    }

    public function destroy(Usuario $usuario, Grupo $grupo): Response
    {
        abort_unless($usuario->gruposOrientados()->detach($grupo->id), 404, 'Recurso no encontrado.');

        return response()->noContent();
    }
}
