<?php

namespace App\Http\Controllers\Api\Catalogos;

use App\Http\Controllers\Controller;
use App\Models\Grupo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** CU-11 */
class GrupoController extends Controller
{
    /** ?periodo_id= filtra; sin él, todos los periodos. */
    public function index(Request $request): JsonResponse
    {
        $grupos = Grupo::with('grado')
            ->when($request->integer('periodo_id'), fn ($q, $id) => $q->where('periodo_id', $id))
            ->get()
            ->sortBy(fn (Grupo $g) => [$g->periodo_id, $g->grado->nombre, $g->letra, $g->turno]);

        return response()->json($grupos->map->aApi()->values());
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'grado_id' => ['required', 'integer', 'exists:grados,id'],
            'plantel_id' => ['required', 'integer', 'exists:planteles,id'],
            'periodo_id' => ['required', 'integer', 'exists:periodos_escolares,id'],
            'letra' => ['required', 'string', 'size:1', 'alpha'],
            'turno' => ['required', 'in:matutino,vespertino'],
        ]);
        $datos['letra'] = mb_strtoupper($datos['letra']);

        abort_if(Grupo::where($datos)->exists(), 409, 'Ese grupo ya existe en el periodo.');

        return response()->json(Grupo::create($datos)->load('grado')->aApi(), 201);
    }
}
