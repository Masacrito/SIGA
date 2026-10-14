<?php

namespace App\Http\Controllers\Api\Catalogos;

use App\Http\Controllers\Controller;
use App\Models\PeriodoEscolar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** CU-11 */
class PeriodoEscolarController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(PeriodoEscolar::orderByDesc('fecha_inicio')->orderByDesc('id')->get());
    }

    /** Solo un periodo activo a la vez: abrir uno nuevo como activo cierra el anterior. */
    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:20', 'unique:periodos_escolares,nombre'],
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_fin' => ['nullable', 'date', 'after:fecha_inicio'],
            'estado' => ['sometimes', 'in:activo,cerrado'],
        ]);
        $datos += ['estado' => 'activo'];

        $periodo = DB::transaction(function () use ($datos) {
            if ($datos['estado'] === 'activo') {
                PeriodoEscolar::where('estado', 'activo')->update(['estado' => 'cerrado']);
            }

            return PeriodoEscolar::create($datos);
        });

        return response()->json($periodo, 201);
    }
}
