<?php

namespace App\Http\Controllers\Api\Catalogos;

use App\Http\Controllers\Controller;
use App\Models\Grado;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** CU-11 */
class GradoController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Grado::orderBy('nombre')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate(['nombre' => ['required', 'string', 'max:10', 'unique:grados,nombre']]);

        return response()->json(Grado::create($datos), 201);
    }
}
