<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asistencia;
use App\Models\Horario;
use App\Models\SesionClase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AsistenciaController extends Controller
{
    private const ESTADOS = ['presente', 'ausente', 'retardo', 'justificado'];

    private const NO_PERTENECE = 'no pertenece al grupo de este horario';

    /** CU-03 online, un alumno. La sesión debe estar abierta (POST /sesiones-clase). */
    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'sesion_clase_id' => ['required', 'integer', 'exists:sesiones_clase,id'],
            'alumno_id' => ['required', 'integer'],
            'estado' => ['required', Rule::in(self::ESTADOS)],
        ]);

        $sesion = $this->sesionAbierta($datos['sesion_clase_id']);

        if (! $sesion->horario->alumnosInscritos([$datos['alumno_id']])) {
            throw ValidationException::withMessages(['alumno_id' => 'El alumno '.self::NO_PERTENECE.'.']);
        }

        if ($sesion->asistencias()->where('alumno_id', $datos['alumno_id'])->exists()) {
            return response()->json(['message' => 'Ya existe un registro de este alumno en esta sesión.'], 409);
        }

        return response()->json($this->crearOnline($sesion, $datos['alumno_id'], $datos['estado'], $request), 201);
    }

    /** CU-03 online, todo el grupo. Errores por fila, sin abortar el resto. */
    public function lote(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'sesion_clase_id' => ['required', 'integer', 'exists:sesiones_clase,id'],
            'registros' => ['required', 'array', 'min:1'],
            'registros.*.alumno_id' => ['required', 'integer', 'distinct'],
            'registros.*.estado' => ['required', Rule::in(self::ESTADOS)],
        ]);

        $sesion = $this->sesionAbierta($datos['sesion_clase_id']);
        $registros = collect($datos['registros']);
        $inscritos = $sesion->horario->alumnosInscritos($registros->pluck('alumno_id')->all());
        $yaRegistrados = $sesion->asistencias()->pluck('alumno_id')->all();

        $asistencias = [];
        $errores = [];
        foreach ($registros as $r) {
            $motivo = match (true) {
                ! in_array($r['alumno_id'], $inscritos) => self::NO_PERTENECE,
                in_array($r['alumno_id'], $yaRegistrados) => 'ya tiene registro en esta sesión',
                default => null,
            };
            $motivo
                ? $errores[] = ['alumno_id' => $r['alumno_id'], 'motivo' => $motivo]
                : $asistencias[] = $this->crearOnline($sesion, $r['alumno_id'], $r['estado'], $request);
        }

        return response()->json(['creadas' => count($asistencias), 'errores' => $errores, 'asistencias' => $asistencias], 201);
    }

    /**
     * CU-04 offline. Idempotente por uuid_local: reintentar el mismo envío es seguro.
     * Un registro de otro uuid_local para el mismo alumno y sesión es "conflicto" y no se sobreescribe.
     */
    public function sincronizar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'listas' => ['required', 'array', 'min:1'],
            'listas.*.horario_id' => ['required', 'integer'],
            'listas.*.fecha' => ['required', 'date_format:Y-m-d'],
            'listas.*.registros' => ['required', 'array', 'min:1'],
            'listas.*.registros.*.uuid_local' => ['required', 'uuid'],
            'listas.*.registros.*.alumno_id' => ['required', 'integer'],
            'listas.*.registros.*.estado' => ['required', Rule::in(self::ESTADOS)],
            'listas.*.registros.*.capturada_en' => ['required', 'date'],
        ]);

        $resultados = [];
        foreach ($datos['listas'] as $lista) {
            $horario = Horario::find($lista['horario_id']);
            $motivoLista = match (true) {
                ! $horario => 'el horario no existe',
                ! $request->user()->can('usar-horario', $horario) => 'el horario no está asignado a este usuario',
                ! $horario->correspondeA($lista['fecha']) => 'la fecha no corresponde al día de este horario',
                default => null,
            };
            $sesion = $motivoLista ? null : SesionClase::abrir($horario->id, $lista['fecha']);
            if ($sesion?->cancelada) {
                $motivoLista = 'la sesión de clase está cancelada';
            }
            $inscritos = $motivoLista ? [] : $horario->alumnosInscritos(array_column($lista['registros'], 'alumno_id'));

            foreach ($lista['registros'] as $r) {
                // ponytail: sin bloqueo; si dos envíos iguales llegan a la vez, el segundo choca con el unique y la app reintenta
                $porUuid = Asistencia::firstWhere('uuid_local', $r['uuid_local']);
                $existente = $sesion?->asistencias()->firstWhere('alumno_id', $r['alumno_id']);
                $resultados[] = ['uuid_local' => $r['uuid_local']] + match (true) {
                    (bool) $porUuid => ['estado_sync' => 'duplicado', 'asistencia_id' => $porUuid->id],
                    (bool) $motivoLista => ['estado_sync' => 'error', 'asistencia_id' => null, 'motivo' => $motivoLista],
                    ! in_array($r['alumno_id'], $inscritos) => ['estado_sync' => 'error', 'asistencia_id' => null, 'motivo' => 'alumno_id '.self::NO_PERTENECE],
                    (bool) $existente => ['estado_sync' => 'conflicto', 'asistencia_id' => $existente->id, 'motivo' => 'ya existe un registro para este alumno en esta sesión con otro uuid_local'],
                    default => ['estado_sync' => 'creado', 'asistencia_id' => $sesion->asistencias()->create([
                        'alumno_id' => $r['alumno_id'],
                        'registrado_por' => $request->user()->id,
                        'estado' => $r['estado'],
                        'uuid_local' => $r['uuid_local'],
                        'capturada_en' => Carbon::parse($r['capturada_en'])->setTimezone(config('app.timezone')),
                        'origen' => 'offline',
                        'sincronizada_en' => now(),
                    ])->id],
                };
            }
        }

        return response()->json(['resultados' => $resultados]);
    }

    public function historial(): JsonResponse
    {
        return $this->noImplementado();
    }

    /** Sesión del docente y no cancelada; si no, 403 / 409. */
    private function sesionAbierta(int $id): SesionClase
    {
        $sesion = SesionClase::with('horario')->findOrFail($id);
        Gate::authorize('usar-horario', $sesion->horario);
        abort_if($sesion->cancelada, 409, "La sesión de clase está cancelada: {$sesion->motivo_cancelacion}");

        return $sesion;
    }

    private function crearOnline(SesionClase $sesion, int $alumnoId, string $estado, Request $request): Asistencia
    {
        return $sesion->asistencias()->create([
            'alumno_id' => $alumnoId,
            'registrado_por' => $request->user()->id,
            'estado' => $estado,
            'capturada_en' => now(),
            'origen' => 'online',
        ]);
    }
}
