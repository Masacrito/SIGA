<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Models\Grupo;
use App\Models\Observacion;
use App\Models\PeriodoEscolar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** CU-08: bitácora de seguimiento tutorial. Pertenencia: solo alumnos de grupos visibles. */
class ObservacionController extends Controller
{
    /** Con alumno_id, toda su bitácora; sin él, las 100 más recientes de los grupos visibles. */
    public function index(Request $request): JsonResponse
    {
        $request->validate(['alumno_id' => ['nullable', 'integer', 'exists:alumnos,id']]);
        $usuario = $request->user();

        if ($alumnoId = $request->integer('alumno_id')) {
            abort_unless($usuario->puedeVerAlumno(Alumno::find($alumnoId)), 403, 'No tienes permiso para realizar esta acción.');
        }

        $observaciones = Observacion::query()
            ->with(['alumno:id,matricula,nombre', 'orientador:id,nombre'])
            ->when($alumnoId,
                fn ($q) => $q->where('alumno_id', $alumnoId),
                fn ($q) => $q->unless($usuario->tienePermiso('ver_todos_los_grupos'), fn ($q) => $q->whereHas('alumno.inscripciones', fn ($i) => $i
                    ->where('periodo_id', PeriodoEscolar::activo()?->id)
                    ->whereIn('grupo_id', Grupo::visiblesPara($usuario)->select('grupos.id'))))
                    ->limit(100))
            ->orderByDesc('fecha')->orderByDesc('id')
            ->get();

        return response()->json($observaciones->map(fn (Observacion $o) => $this->forma($o)));
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'alumno_id' => ['required', 'integer', 'exists:alumnos,id'],
            'texto' => ['required', 'string', 'max:2000'],
        ]);
        abort_unless($request->user()->puedeVerAlumno(Alumno::find($datos['alumno_id'])), 403, 'No tienes permiso para realizar esta acción.');

        $observacion = Observacion::create([
            'alumno_id' => $datos['alumno_id'],
            'orientador_id' => $request->user()->id,
            'fecha' => today(),
            'texto' => trim($datos['texto']),
        ])->load(['alumno:id,matricula,nombre', 'orientador:id,nombre']);

        return response()->json($this->forma($observacion), 201);
    }

    /** Forma Observacion del contrato + quién la escribió y de qué alumno (para listarlas sin más consultas). */
    private function forma(Observacion $o): array
    {
        return [
            'id' => $o->id,
            'alumno_id' => $o->alumno_id,
            'orientador_id' => $o->orientador_id,
            'fecha' => $o->fecha->toDateString(),
            'texto' => $o->texto,
            'alumno' => $o->alumno->only(['id', 'matricula', 'nombre']),
            'orientador' => $o->orientador->only(['id', 'nombre']),
        ];
    }
}
