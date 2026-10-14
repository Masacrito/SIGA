<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Models\Grupo;
use App\Models\PeriodoEscolar;
use App\Support\Semaforo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlumnoController extends Controller
{
    /**
     * Búsqueda por matrícula, nombre o grupo; situacion_academica de la
     * inscripción del periodo activo. Pertenencia: solo alumnos inscritos en
     * grupos que el usuario ve (con ver_todos_los_grupos, cualquiera).
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'matricula' => ['nullable', 'string', 'max:20'],
            'nombre' => ['nullable', 'string', 'max:100'],
            'grupo_id' => ['nullable', 'integer'],
        ]);
        $periodoId = PeriodoEscolar::activo()?->id;
        $usuario = $request->user();

        $alumnos = Alumno::query()
            ->with(['inscripciones' => fn ($q) => $q->where('periodo_id', $periodoId)])
            ->unless($usuario->tienePermiso('ver_todos_los_grupos'), fn ($q) => $q->whereHas('inscripciones', fn ($i) => $i
                ->where('periodo_id', $periodoId)
                ->whereIn('grupo_id', Grupo::visiblesPara($usuario)->select('grupos.id'))))
            ->when($request->input('matricula'), fn ($q, $m) => $q->where('matricula', 'like', "{$m}%"))
            ->when($request->input('nombre'), fn ($q, $n) => $q->where('nombre', 'like', "%{$n}%"))
            ->when($request->integer('grupo_id'), fn ($q, $g) => $q->whereHas('inscripciones', fn ($q) => $q->where('grupo_id', $g)->where('periodo_id', $periodoId)))
            ->orderBy('nombre')
            ->limit(50) // es un buscador: con 50 basta, se afina con los filtros
            ->get();

        return response()->json($alumnos->map(fn (Alumno $a) => $a->only(['id', 'matricula', 'nombre', 'activo']) + [
            'situacion_academica' => $a->inscripciones->first()?->situacion_academica,
        ]));
    }

    /** Expediente: datos, grupo actual, riesgo del periodo y tutores. */
    public function show(Request $request, Alumno $alumno): JsonResponse
    {
        $periodo = PeriodoEscolar::activo();
        $inscripcion = $alumno->inscripciones()->where('periodo_id', $periodo?->id)->with('grupo.grado')->first();
        $visible = $request->user()->tienePermiso('ver_todos_los_grupos')
            || ($inscripcion && Grupo::visiblesPara($request->user())->whereKey($inscripcion->grupo_id)->exists());
        abort_unless($visible, 403, 'No tienes permiso para realizar esta acción.');

        return response()->json($alumno->only(['id', 'matricula', 'nombre', 'activo']) + [
            'situacion_academica' => $inscripcion?->situacion_academica,
            'grupo' => $inscripcion?->grupo->aApi(),
            'riesgo' => $inscripcion ? Semaforo::calcular([$alumno->id], $periodo->id)[$alumno->id] : null,
            'tutores' => $alumno->padresTutores()->orderByDesc('es_principal')->get()->map(fn ($t) => $t->only(['id', 'nombre', 'telefono', 'correo', 'canal_preferido']) + [
                'parentesco' => $t->pivot->parentesco,
                'es_principal' => (bool) $t->pivot->es_principal,
            ]),
        ]);
    }
}
