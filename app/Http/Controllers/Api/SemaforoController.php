<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Grupo;
use App\Models\Inscripcion;
use App\Models\PeriodoEscolar;
use App\Support\Semaforo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SemaforoController extends Controller
{
    /**
     * CU-06: alumnos activos de los grupos que ve el usuario, del más al menos
     * faltista. Las bajas no aparecen.
     */
    public function index(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'grupo_id' => ['nullable', 'integer', 'exists:grupos,id'],
            'nivel' => ['nullable', 'in:bajo,medio,alto'],
        ]);
        $periodo = PeriodoEscolar::activo();
        abort_unless($periodo, 422, 'No hay un periodo escolar activo.');

        $grupos = Grupo::visiblesPara($request->user())->where('periodo_id', $periodo->id)->with('grado')->get()->keyBy('id');
        if (isset($datos['grupo_id'])) {
            abort_unless($grupos->has($datos['grupo_id']), 403, 'No tienes permiso para realizar esta acción.');
            $grupos = $grupos->only([$datos['grupo_id']]);
        }

        $inscripciones = Inscripcion::with('alumno')
            ->where('periodo_id', $periodo->id)
            ->whereIn('grupo_id', $grupos->keys())
            ->whereRelation('alumno', 'activo', true)
            ->get();
        $riesgo = Semaforo::calcular($inscripciones->pluck('alumno_id')->all(), $periodo->id);

        $filas = $inscripciones
            ->map(fn (Inscripcion $i) => [
                'alumno' => $i->alumno->only(['id', 'matricula', 'nombre', 'activo']) + ['situacion_academica' => $i->situacion_academica],
                'grupo' => $grupos[$i->grupo_id]->aApi(),
            ] + $riesgo[$i->alumno_id])
            ->when($datos['nivel'] ?? null, fn ($c, $nivel) => $c->where('nivel_riesgo', $nivel))
            ->sortBy([['porcentaje_inasistencia', 'desc'], ['alumno.nombre', 'asc']])
            ->values();

        return response()->json($filas);
    }
}
