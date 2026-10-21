<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Grupo;
use App\Models\Horario;
use App\Models\PeriodoEscolar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

/**
 * CU-02 (mis horarios) y carga manual de horarios: el horario es lo que une
 * al docente con sus grupos (grupo + materia + docente + día + hora).
 */
class HorarioController extends Controller
{
    private const DIAS = [1 => 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'];

    /** CU-02: horarios del docente autenticado en el periodo activo. Sin fila en docentes → []. */
    public function misHorarios(Request $request): JsonResponse
    {
        $docenteId = $request->user()->docente?->id;

        $horarios = Horario::with(['grupo.grado', 'materia'])
            ->where('docente_id', $docenteId)
            ->whereRelation('grupo', 'periodo_id', PeriodoEscolar::activo()?->id)
            ->orderBy('dia_semana')->orderBy('hora_inicio')
            ->get();

        return response()->json($horarios->map(fn (Horario $h) => [
            'id' => $h->id,
            'grupo' => $h->grupo->aApi(),
            'materia' => $h->materia->only(['id', 'clave', 'nombre']),
            'dia_semana' => $h->dia_semana,
            'hora_inicio' => substr($h->hora_inicio, 0, 5),
            'hora_fin' => substr($h->hora_fin, 0, 5),
        ]));
    }

    /** Horario de un grupo o de un docente (uno de los dos), del periodo del grupo / activo. */
    public function index(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'grupo_id' => ['required_without:docente_id', 'nullable', 'integer', 'exists:grupos,id'],
            'docente_id' => ['required_without:grupo_id', 'nullable', 'integer', 'exists:docentes,id'],
        ]);

        $horarios = Horario::with(['grupo.grado', 'materia', 'docente.usuario'])
            ->when($datos['grupo_id'] ?? null, fn ($q, $id) => $q->where('grupo_id', $id))
            ->when($datos['docente_id'] ?? null, fn ($q, $id) => $q
                ->where('docente_id', $id)
                ->whereRelation('grupo', 'periodo_id', PeriodoEscolar::activo()?->id))
            ->withCount('sesiones')
            ->orderBy('dia_semana')->orderBy('hora_inicio')
            ->get();

        return response()->json($horarios->map->aApiAdmin());
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $this->validar($request);
        $this->revisarChoques($datos);

        $horario = Horario::create($datos)->loadCount('sesiones');

        return response()->json($horario->load(['grupo.grado', 'materia', 'docente.usuario'])->aApiAdmin(), 201);
    }

    /**
     * Con listas ya capturadas solo cambian docente y horas: mover el grupo, la
     * materia o el día movería también la asistencia ya registrada.
     */
    public function update(Request $request, Horario $horario): JsonResponse
    {
        $datos = $this->validar($request);

        if ($horario->sesiones()->exists()) {
            foreach (['grupo_id', 'materia_id', 'dia_semana'] as $campo) {
                abort_if((int) $datos[$campo] !== (int) $horario->{$campo}, 409,
                    'Esta clase ya tiene listas capturadas: solo se pueden cambiar el docente y las horas. Para otro grupo, materia o día, crea una clase nueva.');
            }
        }
        $this->revisarChoques($datos, $horario);

        $horario->update($datos);

        return response()->json($horario->loadCount('sesiones')->load(['grupo.grado', 'materia', 'docente.usuario'])->aApiAdmin());
    }

    public function destroy(Horario $horario): Response
    {
        abort_if($horario->sesiones()->exists(), 409,
            'Esta clase ya tiene listas capturadas y no se puede eliminar; se perdería su asistencia.');
        $horario->delete();

        return response()->noContent();
    }

    private function validar(Request $request): array
    {
        $datos = $request->validate([
            'grupo_id' => ['required', 'integer', 'exists:grupos,id'],
            'materia_id' => ['required', 'integer', 'exists:materias,id'],
            'docente_id' => ['required', 'integer', 'exists:docentes,id'],
            'dia_semana' => ['required', 'integer', 'between:1,6'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'hora_fin' => ['required', 'date_format:H:i', 'after:hora_inicio'],
        ], [
            'hora_fin.after' => 'La hora de fin debe ser después de la de inicio.',
            'dia_semana.between' => 'El día debe ser de lunes a sábado.',
        ]);

        $grupo = Grupo::find($datos['grupo_id']);
        if ($grupo->periodo_id !== PeriodoEscolar::activo()?->id) {
            throw ValidationException::withMessages(['grupo_id' => 'El grupo no es del periodo activo.']);
        }
        // Mismo formato que guarda la columna TIME
        $datos['hora_inicio'] .= ':00';
        $datos['hora_fin'] .= ':00';

        return $datos;
    }

    /** Ni el grupo ni el docente pueden estar en dos clases a la vez (mismo día, horas encimadas). */
    private function revisarChoques(array $datos, ?Horario $actual = null): void
    {
        // Comparación como texto HH:MM: igual en MySQL (TIME) y en SQLite (texto con o sin segundos)
        $encimados = fn () => Horario::with(['grupo.grado', 'materia'])
            ->where('dia_semana', $datos['dia_semana'])
            ->whereRaw('substr(hora_inicio, 1, 5) < ?', [substr($datos['hora_fin'], 0, 5)])
            ->whereRaw('substr(hora_fin, 1, 5) > ?', [substr($datos['hora_inicio'], 0, 5)])
            ->when($actual, fn ($q) => $q->whereKeyNot($actual->id));

        $dia = self::DIAS[$datos['dia_semana']];
        $describe = fn (Horario $h) => sprintf('%s (%s–%s)', $h->materia->nombre, substr($h->hora_inicio, 0, 5), substr($h->hora_fin, 0, 5));

        if ($h = $encimados()->where('grupo_id', $datos['grupo_id'])->first()) {
            abort(409, "El grupo ya tiene {$describe($h)} el {$dia}.");
        }

        $periodoId = Grupo::whereKey($datos['grupo_id'])->value('periodo_id');
        if ($h = $encimados()->where('docente_id', $datos['docente_id'])->whereRelation('grupo', 'periodo_id', $periodoId)->first()) {
            abort(409, "El docente ya da {$describe($h)} en {$h->grupo->grado->nombre}° {$h->grupo->letra} el {$dia}.");
        }
    }
}
