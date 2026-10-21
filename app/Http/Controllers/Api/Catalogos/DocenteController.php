<?php

namespace App\Http\Controllers\Api\Catalogos;

use App\Http\Controllers\Controller;
use App\Models\Docente;
use Illuminate\Http\JsonResponse;

/** Docentes que se pueden poner en un horario: cuenta activa y con rol docente. */
class DocenteController extends Controller
{
    public function index(): JsonResponse
    {
        $docentes = Docente::with('usuario')
            ->whereHas('usuario', fn ($q) => $q->where('activo', true)->whereRelation('rol', 'nombre', 'docente'))
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
