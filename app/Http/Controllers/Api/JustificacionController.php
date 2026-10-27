<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asistencia;
use App\Models\Justificacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * CU-17: el orientador autoriza un justificante sobre una falta o un retardo.
 * La asistencia pasa a 'justificado': sigue contando como clase, ya no como falta.
 */
class JustificacionController extends Controller
{
    public function store(Request $request, Asistencia $asistencia): JsonResponse
    {
        $datos = $request->validate([
            'motivo' => ['required', 'string', 'max:255'],
            'evidencia_url' => ['nullable', 'url', 'max:255'],
        ]);
        $asistencia->load('sesion.horario.grupo');
        abort_unless($request->user()->puedeVerGrupo($asistencia->sesion->horario->grupo), 403, 'No tienes permiso para realizar esta acción.');

        if ($asistencia->justificacion()->exists()) {
            return response()->json(['message' => 'Esta asistencia ya tiene un justificante.'], 409);
        }
        // 'justificado' sin justificante (el docente lo marcó al pasar lista) sí se puede documentar
        if ($asistencia->estado === 'presente') {
            throw ValidationException::withMessages(['asistencia' => 'Solo se justifican faltas o retardos.']);
        }

        $justificacion = DB::transaction(function () use ($asistencia, $datos, $request) {
            $asistencia->update(['estado' => 'justificado']);

            return Justificacion::create([
                'asistencia_id' => $asistencia->id,
                'motivo' => trim($datos['motivo']),
                'evidencia_url' => $datos['evidencia_url'] ?? null,
                'autorizada_por' => $request->user()->id,
                'fecha_autorizacion' => now(),
            ]);
        });

        return response()->json($justificacion->only(['id', 'asistencia_id', 'motivo', 'evidencia_url', 'autorizada_por']) + [
            'fecha_autorizacion' => $justificacion->fecha_autorizacion->toIso8601String(),
            'estado' => 'justificado',
        ], 201);
    }
}
