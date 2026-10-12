<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Horario;
use App\Models\SesionClase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SesionClaseController extends Controller
{
    /** CU-03: abre (o devuelve) la sesión de horario+fecha. Idempotente. */
    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'horario_id' => ['required', 'integer', 'exists:horarios,id'],
            'fecha' => ['required', 'date_format:Y-m-d'],
        ]);

        $horario = Horario::findOrFail($datos['horario_id']);
        Gate::authorize('usar-horario', $horario);

        if (! $horario->correspondeA($datos['fecha'])) {
            throw ValidationException::withMessages(['fecha' => 'La fecha no corresponde al día de este horario.']);
        }

        $sesion = SesionClase::abrir($horario->id, $datos['fecha']);

        if ($sesion->cancelada) {
            return response()->json(['message' => "La sesión de clase está cancelada: {$sesion->motivo_cancelacion}"], 409);
        }

        return response()->json([
            'id' => $sesion->id,
            'horario_id' => $sesion->horario_id,
            'fecha' => $sesion->fecha->toDateString(),
            'cancelada' => $sesion->cancelada,
        ], 201);
    }
}
