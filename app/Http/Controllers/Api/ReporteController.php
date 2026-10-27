<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Configuracion;
use App\Models\Grupo;
use App\Models\Inscripcion;
use App\Support\Reporte\Pdf;
use App\Support\Reporte\Xlsx;
use App\Support\Semaforo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * CU-07: reporte de un grupo en su periodo — asistencia con el mismo cálculo del
 * semáforo y promedio de calificaciones numéricas. PDF para imprimir, Excel para trabajarlo.
 * Las bajas no aparecen (igual que en el semáforo).
 */
class ReporteController extends Controller
{
    private const NIVEL = ['bajo' => 'Bajo', 'medio' => 'Medio', 'alto' => 'Alto'];

    public function generar(Request $request): Response
    {
        $datos = $request->validate([
            'grupo_id' => ['required', 'integer', 'exists:grupos,id'],
            'periodo_id' => ['nullable', 'integer', 'exists:periodos_escolares,id'],
            'formato' => ['nullable', 'in:pdf,excel'],
        ]);
        $grupo = Grupo::with(['grado', 'plantel', 'periodo'])->find($datos['grupo_id']);
        abort_unless($request->user()->puedeVerGrupo($grupo), 403, 'No tienes permiso para realizar esta acción.');
        if (isset($datos['periodo_id']) && $datos['periodo_id'] !== $grupo->periodo_id) {
            throw ValidationException::withMessages(['periodo_id' => 'El grupo no es de ese periodo.']);
        }

        $inscripciones = Inscripcion::with('alumno')
            ->where('grupo_id', $grupo->id)->where('periodo_id', $grupo->periodo_id)
            ->whereRelation('alumno', 'activo', true)
            ->get()->sortBy('alumno.nombre', SORT_NATURAL | SORT_FLAG_CASE)->values();
        $ids = $inscripciones->pluck('alumno_id')->all();
        $riesgo = $ids ? Semaforo::calcular($ids, $grupo->periodo_id) : [];
        $promedios = $this->promedios($ids, $grupo->periodo_id);

        $nombreGrupo = "{$grupo->grado->nombre}° {$grupo->letra} ".$grupo->turno;
        $conteo = array_count_values(array_column($riesgo, 'nivel_riesgo'));
        $retardos = (int) Configuracion::valor('retardos_equivalen_falta', 0);
        $reporte = [
            'titulo' => "Reporte de asistencia — {$nombreGrupo}",
            'lineas' => [
                "{$grupo->plantel->nombre} ({$grupo->plantel->clave}) · Periodo {$grupo->periodo->nombre}",
                'Generado el '.now()->format('d/m/Y H:i').' por '.$request->user()->nombre,
            ],
            'columnas' => [
                ['titulo' => 'No.', 'ancho' => 28, 'alinear' => 'der'],
                ['titulo' => 'Matrícula', 'ancho' => 80],
                ['titulo' => 'Alumno', 'ancho' => 210],
                ['titulo' => 'Situación', 'ancho' => 62],
                ['titulo' => 'Clases', 'ancho' => 42, 'alinear' => 'der'],
                ['titulo' => 'Faltas', 'ancho' => 42, 'alinear' => 'der'],
                ['titulo' => 'Retardos', 'ancho' => 50, 'alinear' => 'der'],
                ['titulo' => 'Justificadas', 'ancho' => 62, 'alinear' => 'der'],
                ['titulo' => '% inasistencia', 'ancho' => 74, 'alinear' => 'der', 'decimales' => 1],
                ['titulo' => 'Riesgo', 'ancho' => 56],
                ['titulo' => 'Promedio', 'ancho' => 54, 'alinear' => 'der', 'decimales' => 1],
            ],
            'filas' => $inscripciones->map(fn ($i, $n) => [
                $n + 1,
                $i->alumno->matricula,
                $i->alumno->nombre,
                ucfirst($i->situacion_academica),
                $riesgo[$i->alumno_id]['clases'],
                $riesgo[$i->alumno_id]['ausencias'],
                $riesgo[$i->alumno_id]['retardos'],
                $riesgo[$i->alumno_id]['justificadas'],
                (float) $riesgo[$i->alumno_id]['porcentaje_inasistencia'],
                ['texto' => self::NIVEL[$riesgo[$i->alumno_id]['nivel_riesgo']], 'color' => $riesgo[$i->alumno_id]['color_hex']],
                $promedios[$i->alumno_id] ?? '—',
            ])->all(),
            'pie' => [
                count($ids).' alumnos · riesgo alto: '.($conteo['alto'] ?? 0).' · medio: '.($conteo['medio'] ?? 0).' · bajo: '.($conteo['bajo'] ?? 0),
                '% inasistencia = (faltas'.($retardos > 0 ? " + retardos ÷ {$retardos}" : '').') ÷ clases con lista, sobre todo el periodo. Las justificadas cuentan como clase, no como falta.',
                'Promedio: calificaciones numéricas registradas del periodo (todas las materias y parciales).',
            ],
        ];

        $archivo = 'SIGA_asistencia_'.Str::slug("{$grupo->grado->nombre}{$grupo->letra} {$grupo->turno} {$grupo->periodo->nombre}", '_');
        [$contenido, $tipo, $ext] = ($datos['formato'] ?? 'pdf') === 'excel'
            ? [Xlsx::generar($reporte, $nombreGrupo), 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'xlsx']
            : [Pdf::generar($reporte), 'application/pdf', 'pdf'];

        return response($contenido, 200, [
            'Content-Type' => $tipo,
            'Content-Disposition' => "attachment; filename=\"{$archivo}.{$ext}\"",
        ]);
    }

    /** Promedio de las calificaciones numéricas del periodo, por alumno (1 decimal). */
    private function promedios(array $alumnoIds, int $periodoId): array
    {
        return DB::table('calificaciones as c')
            ->join('materias as m', 'm.id', '=', 'c.materia_id')
            ->where('m.tipo_evaluacion', 'numerica')
            ->where('c.periodo_id', $periodoId)
            ->whereIn('c.alumno_id', $alumnoIds)
            ->groupBy('c.alumno_id')
            ->selectRaw('c.alumno_id, ROUND(AVG(CAST(c.calificacion AS DECIMAL(5,1))), 1) AS promedio')
            ->pluck('promedio', 'alumno_id')
            ->map(fn ($p) => (float) $p)
            ->all();
    }
}
