<?php

namespace App\Http\Controllers\Api\Catalogos;

use App\Http\Controllers\Controller;
use App\Models\Docente;
use App\Models\Rol;
use Illuminate\Http\JsonResponse;

/** Quién puede dar una clase: cuenta activa con rol docente u orientador (también dan clase). */
class DocenteController extends Controller
{
    public function index(): JsonResponse
    {
        $docentes = Docente::with('usuario')
            ->whereHas('usuario', fn ($q) => $q->where('activo', true)->whereHas('rol', fn ($q) => $q->whereIn('nombre', Rol::FRENTE_A_GRUPO)))
            ->get()
            ->sortBy('usuario.nombre');

        return response()->json($docentes->map(fn (Docente $d) => [
            'id' => $d->id,
            'usuario_id' => $d->usuario_id,
            'nombre' => $d->usuario->nombre,
            'username' => $d->usuario->username,
            'numero_empleado' => $d->numero_empleado,
        ])->values());
    }
}
