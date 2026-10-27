<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Models\Grupo;
use App\Models\Inscripcion;
use App\Models\PeriodoEscolar;
use App\Support\Semaforo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
            // Es un buscador: con 50 basta, se afina con los filtros. Por grupo es la lista completa
            ->limit($request->integer('grupo_id') ? 100 : 50)
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
        abort_unless($request->user()->puedeVerAlumno($alumno), 403, 'No tienes permiso para realizar esta acción.');

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

    /** Alta manual: el alumno y su inscripción en un grupo del periodo activo (así aparece en la lista). */
    public function store(Request $request): JsonResponse
    {
        $datos = $this->validar($request);

        $alumno = DB::transaction(function () use ($datos) {
            $alumno = Alumno::create($datos + ['activo' => true]);
            $this->inscribir($alumno, $datos);

            return $alumno;
        });

        return response()->json($this->forma($alumno), 201);
    }

    /**
     * Edición, baja (activo = false) y cambio de grupo. Cambiar de grupo mueve
     * la inscripción del periodo activo; su asistencia anterior se conserva y
     * sigue contando para el semáforo.
     */
    public function update(Request $request, Alumno $alumno): JsonResponse
    {
        $datos = $this->validar($request, $alumno);

        DB::transaction(function () use ($alumno, $datos) {
            $alumno->update($datos);
            $this->inscribir($alumno, $datos);
        });

        return response()->json($this->forma($alumno));
    }

    private function validar(Request $request, ?Alumno $alumno = null): array
    {
        $datos = $request->validate([
            'matricula' => ['required', 'string', 'max:20', Rule::unique('alumnos', 'matricula')->ignore($alumno)],
            'nombre' => ['required', 'string', 'max:200'],
            'activo' => ['sometimes', 'boolean'],
            // Al editar, sin grupo_id la inscripción no cambia
            'grupo_id' => [$alumno ? 'sometimes' : 'required', 'integer', 'exists:grupos,id'],
            'situacion_academica' => ['sometimes', 'in:regular,irregular,recursador,extemporaneo'],
        ]);
        $datos['nombre'] = preg_replace('/\s+/u', ' ', trim($datos['nombre']));

        if (isset($datos['grupo_id']) && Grupo::whereKey($datos['grupo_id'])->value('periodo_id') !== PeriodoEscolar::activo()?->id) {
            throw ValidationException::withMessages(['grupo_id' => 'El grupo no es del periodo activo.']);
        }

        return $datos;
    }

    /** Una inscripción por alumno y periodo (llave única): se crea o se actualiza. */
    private function inscribir(Alumno $alumno, array $datos): void
    {
        $periodoId = PeriodoEscolar::activo()?->id;
        $cambios = array_intersect_key($datos, ['grupo_id' => true, 'situacion_academica' => true]);
        $inscripcion = Inscripcion::firstWhere(['alumno_id' => $alumno->id, 'periodo_id' => $periodoId]);

        if ($inscripcion) {
            $inscripcion->update($cambios);
        } elseif (isset($cambios['grupo_id'])) {
            Inscripcion::create($cambios + [
                'alumno_id' => $alumno->id,
                'periodo_id' => $periodoId,
                'situacion_academica' => 'regular',
                'fecha_inscripcion' => today(),
            ]);
        }
    }

    /** Forma AlumnoAdmin: Alumno + su grupo del periodo activo. */
    private function forma(Alumno $alumno): array
    {
        $inscripcion = $alumno->inscripciones()->where('periodo_id', PeriodoEscolar::activo()?->id)->with('grupo.grado')->first();

        return $alumno->only(['id', 'matricula', 'nombre', 'activo']) + [
            'situacion_academica' => $inscripcion?->situacion_academica,
            'grupo' => $inscripcion?->grupo->aApi(),
        ];
    }
}
