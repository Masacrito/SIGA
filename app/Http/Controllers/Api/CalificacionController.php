<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Models\Calificacion;
use App\Models\PeriodoEscolar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CalificacionController extends Controller
{
    /** CU-15: boleta del alumno en un periodo (por defecto el activo), por materia y parcial. */
    public function porAlumno(Request $request, Alumno $alumno): JsonResponse
    {
        $request->validate(['periodo_id' => ['nullable', 'integer', 'exists:periodos_escolares,id']]);
        abort_unless($request->user()->puedeVerAlumno($alumno), 403, 'No tienes permiso para realizar esta acción.');

        $calificaciones = $alumno->calificaciones()
            ->with(['materia', 'editadoPor:id,nombre'])
            ->where('periodo_id', $request->integer('periodo_id') ?: PeriodoEscolar::activo()?->id)
            ->get()
            ->sortBy([fn ($a, $b) => strcmp($a->materia->nombre, $b->materia->nombre), ['parcial', 'asc']])
            ->values();

        return response()->json($calificaciones->map(fn (Calificacion $c) => $this->forma($c)));
    }

    /**
     * CU-16: corrección manual. Numérica: 0 a la escala activa (10 o 100), con un decimal
     * como máximo; alfanumérica: A o NA. Queda origen = manual y quién la editó.
     */
    public function update(Request $request, Calificacion $calificacion): JsonResponse
    {
        $request->validate(['calificacion' => ['required', 'string', 'max:5']]);
        $calificacion->load('alumno', 'materia');
        abort_unless($request->user()->puedeVerAlumno($calificacion->alumno), 403, 'No tienes permiso para realizar esta acción.');

        try {
            $valor = Calificacion::normalizar($request->input('calificacion'), $calificacion->materia->tipo_evaluacion);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['calificacion' => $e->getMessage()]);
        }

        $calificacion->update(['calificacion' => $valor, 'origen' => 'manual', 'editado_por' => $request->user()->id]);

        return response()->json($this->forma($calificacion->load('editadoPor:id,nombre')));
    }

    /** Forma Calificacion del contrato + la materia y el nombre de quien la editó. */
    private function forma(Calificacion $c): array
    {
        return $c->only(['id', 'alumno_id', 'materia_id', 'periodo_id', 'parcial', 'calificacion', 'tipo', 'origen']) + [
            'materia' => $c->materia->only(['id', 'clave', 'nombre', 'tipo_evaluacion']),
            'editado_por' => $c->editadoPor?->only(['id', 'nombre']),
            'actualizada_en' => $c->updated_at?->toIso8601String(),
        ];
    }
}
