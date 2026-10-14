<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Models\PadreTutor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/** CU-09: vínculo alumno ↔ padre/tutor. */
class AlumnoPadreController extends Controller
{
    public function index(Alumno $alumno): JsonResponse
    {
        return response()->json($alumno->padresTutores()->orderByDesc('es_principal')->orderBy('nombre')->get()
            ->map(fn (PadreTutor $p) => $p->only(['id', 'nombre', 'telefono', 'correo', 'canal_preferido']) + [
                'parentesco' => $p->pivot->parentesco,
                'es_principal' => (bool) $p->pivot->es_principal,
            ]));
    }

    public function store(Request $request, Alumno $alumno): Response
    {
        $datos = $request->validate([
            'padre_tutor_id' => ['required', 'integer', 'exists:padres_tutores,id'],
            'parentesco' => ['nullable', 'string', 'max:50'],
            'es_principal' => ['sometimes', 'boolean'],
        ]);
        abort_if($alumno->padresTutores()->whereKey($datos['padre_tutor_id'])->exists(), 409, 'Ese tutor ya está vinculado al alumno.');

        $principal = (bool) ($datos['es_principal'] ?? false);

        DB::transaction(function () use ($alumno, $datos, $principal) {
            // Un solo tutor principal por alumno: es a quien le llegan los avisos
            if ($principal) {
                $alumno->padresTutores()->newPivotQuery()->update(['es_principal' => false]);
            }
            $alumno->padresTutores()->attach($datos['padre_tutor_id'], [
                'parentesco' => $datos['parentesco'] ?? null,
                'es_principal' => $principal,
            ]);
        });

        return response()->noContent(201);
    }

    public function destroy(Alumno $alumno, PadreTutor $padreTutor): Response
    {
        abort_unless($alumno->padresTutores()->detach($padreTutor->id), 404, 'Recurso no encontrado.');

        return response()->noContent();
    }
}
