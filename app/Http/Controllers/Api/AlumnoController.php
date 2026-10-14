<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Models\PeriodoEscolar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlumnoController extends Controller
{
    /** Búsqueda por matrícula, nombre o grupo; situacion_academica de la inscripción del periodo activo. */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'matricula' => ['nullable', 'string', 'max:20'],
            'nombre' => ['nullable', 'string', 'max:100'],
            'grupo_id' => ['nullable', 'integer'],
        ]);
        $periodoId = PeriodoEscolar::activo()?->id;

        $alumnos = Alumno::query()
            ->with(['inscripciones' => fn ($q) => $q->where('periodo_id', $periodoId)])
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
}
