<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PadreTutor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** CU-09: contactos de padres/tutores. */
class PadreTutorController extends Controller
{
    /** ?buscar= filtra por nombre, teléfono o correo. */
    public function index(Request $request): JsonResponse
    {
        // shortcut: sin paginar; con miles de tutores, paginar aquí y en la pantalla
        $tutores = PadreTutor::withCount('alumnos')
            ->when($request->string('buscar')->trim()->value(), fn ($q, $t) => $q->where(fn ($q) => $q
                ->where('nombre', 'like', "%{$t}%")
                ->orWhere('telefono', 'like', "%{$t}%")
                ->orWhere('correo', 'like', "%{$t}%")))
            ->orderBy('nombre')
            ->get();

        return response()->json($tutores);
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(PadreTutor::create($this->validar($request)), 201);
    }

    public function update(Request $request, PadreTutor $padreTutor): JsonResponse
    {
        $padreTutor->update($this->validar($request));

        return response()->json($padreTutor);
    }

    /** Sin push en esta fase (2026-09-18): el canal debe tener a dónde llegar. */
    private function validar(Request $request): array
    {
        $request->mergeIfMissing(['canal_preferido' => 'sms']);

        return $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'canal_preferido' => ['required', 'in:sms,email'],
            'telefono' => ['nullable', 'string', 'regex:/^[0-9 +()-]{7,20}$/', 'required_if:canal_preferido,sms'],
            'correo' => ['nullable', 'email', 'max:160', 'required_if:canal_preferido,email'],
        ]);
    }
}
