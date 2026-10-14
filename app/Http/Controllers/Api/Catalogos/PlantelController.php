<?php

namespace App\Http\Controllers\Api\Catalogos;

use App\Http\Controllers\Controller;
use App\Models\Plantel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** CU-11 */
class PlantelController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Plantel::orderBy('nombre')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'clave' => ['required', 'string', 'max:20', 'unique:planteles,clave'],
            'nombre' => ['required', 'string', 'max:150'],
        ]);

        return response()->json(Plantel::create($datos), 201);
    }
}
