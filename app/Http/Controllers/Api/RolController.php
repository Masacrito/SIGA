<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rol;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RolController extends Controller
{
    /** CU-10: roles con sus permisos. */
    public function index(): JsonResponse
    {
        return response()->json(Rol::with('permisos')->orderBy('id')->get());
    }

    /** CU-10: reemplaza el conjunto de permisos del rol. */
    public function actualizarPermisos(Request $request, Rol $rol): JsonResponse
    {
        $datos = $request->validate([
            'permiso_ids' => ['present', 'array'],
            'permiso_ids.*' => ['integer', 'distinct', 'exists:permisos,id'],
        ]);

        $rol->permisos()->sync($datos['permiso_ids']);

        return response()->json($rol->load('permisos'));
    }
}
