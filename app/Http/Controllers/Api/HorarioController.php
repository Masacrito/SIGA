<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Horario;
use App\Models\PeriodoEscolar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HorarioController extends Controller
{
    /** CU-02: horarios del docente autenticado en el periodo activo. Sin fila en docentes → []. */
    public function misHorarios(Request $request): JsonResponse
    {
        $docenteId = $request->user()->docente?->id;

        $horarios = Horario::with(['grupo.grado', 'materia'])
            ->where('docente_id', $docenteId)
            ->whereRelation('grupo', 'periodo_id', PeriodoEscolar::activo()?->id)
            ->orderBy('dia_semana')->orderBy('hora_inicio')
            ->get();

        return response()->json($horarios->map(fn (Horario $h) => [
            'id' => $h->id,
            'grupo' => [
                'id' => $h->grupo->id,
                'grado' => $h->grupo->grado->nombre,
                'letra' => $h->grupo->letra,
                'turno' => $h->grupo->turno,
                'plantel_id' => $h->grupo->plantel_id,
                'periodo_id' => $h->grupo->periodo_id,
            ],
            'materia' => $h->materia->only(['id', 'clave', 'nombre']),
            'dia_semana' => $h->dia_semana,
            'hora_inicio' => substr($h->hora_inicio, 0, 5),
            'hora_fin' => substr($h->hora_fin, 0, 5),
        ]));
    }
}
