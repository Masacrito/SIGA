<?php

namespace App\Support;

use App\Models\Configuracion;
use App\Models\UmbralRiesgo;
use Illuminate\Support\Facades\DB;

/**
 * Riesgo de inasistencia por alumno (CU-06). Reglas acordadas (2026-10-14):
 *  - % = faltas / clases con lista, sobre TODO el periodo (aunque cambie de grupo).
 *  - Faltas = ausencias + retardos ÷ retardos_equivalen_falta (3 → 1). Justificadas no cuentan
 *    como falta, pero sí como clase.
 *  - Nivel: porcentaje_min <= % < porcentaje_max (alto incluye el 100).
 *  - faltas_consecutivas de un nivel = días hábiles seguidos, los más recientes, en que no
 *    vino a ninguna clase; si se alcanza, sube a ese nivel aunque el % sea menor.
 * Lo usan el semáforo y el expediente; las alertas automáticas (s10a) lo usarán igual.
 */
final class Semaforo
{
    private const ORDEN = ['bajo' => 0, 'medio' => 1, 'alto' => 2];

    /**
     * @param  int[]  $alumnoIds
     * @return array<int, array{clases: int, ausencias: int, retardos: int, justificadas: int, faltas: int, porcentaje_inasistencia: float, racha_dias: int, nivel_riesgo: string, color_hex: string}>
     */
    public static function calcular(array $alumnoIds, int $periodoId): array
    {
        $umbrales = UmbralRiesgo::where('periodo_id', $periodoId)->where('activo', true)->get();
        if ($umbrales->count() !== 3) {
            $umbrales = UmbralRiesgo::whereNull('periodo_id')->where('activo', true)->get();
        }
        $umbrales = $umbrales->sortBy(fn ($u) => self::ORDEN[$u->nivel])->values();
        $retardosPorFalta = (int) Configuracion::valor('retardos_equivalen_falta', 0);

        $vacio = ['clases' => 0, 'ausencias' => 0, 'retardos' => 0, 'justificadas' => 0, 'dias' => []];
        $datos = array_fill_keys($alumnoIds, $vacio);

        // Una sola pasada: las sesiones canceladas no tienen asistencias, así que no cuentan
        DB::table('asistencias as a')
            ->join('sesiones_clase as s', 's.id', '=', 'a.sesion_clase_id')
            ->join('horarios as h', 'h.id', '=', 's.horario_id')
            ->join('grupos as g', 'g.id', '=', 'h.grupo_id')
            ->where('g.periodo_id', $periodoId)
            ->whereIn('a.alumno_id', $alumnoIds)
            ->orderBy('a.alumno_id')->orderByDesc('s.fecha')
            ->select('a.alumno_id', 'a.estado', 's.fecha')
            ->cursor() // una sola consulta, fila por fila: memoria constante
            ->each(function ($fila) use (&$datos) {
                $d = &$datos[$fila->alumno_id];
                $d['clases']++;
                match ($fila->estado) {
                    'ausente' => $d['ausencias']++,
                    'retardo' => $d['retardos']++,
                    'justificado' => $d['justificadas']++,
                    default => null,
                };
                $dia = substr($fila->fecha, 0, 10);
                // true mientras todas sus clases de ese día sean 'ausente'
                $d['dias'][$dia] = ($d['dias'][$dia] ?? true) && $fila->estado === 'ausente';
            });

        $resultado = [];
        foreach ($datos as $alumnoId => $d) {
            $faltas = $d['ausencias'] + ($retardosPorFalta > 0 ? intdiv($d['retardos'], $retardosPorFalta) : 0);
            // faltas * 100 primero: 3 de 30 da 10.0 exacto y cae en "medio", no en "bajo"
            $porcentaje = $d['clases'] > 0 ? $faltas * 100 / $d['clases'] : 0.0;

            $racha = 0;
            foreach ($d['dias'] as $faltoTodoElDia) { // del día más reciente hacia atrás
                if (! $faltoTodoElDia) {
                    break;
                }
                $racha++;
            }

            $nivel = $umbrales->first(fn ($u) => $porcentaje >= $u->porcentaje_min
                && ($porcentaje < $u->porcentaje_max || $u->nivel === 'alto')) ?? $umbrales->first();
            foreach ($umbrales as $u) {
                if ($u->faltas_consecutivas && $racha >= $u->faltas_consecutivas && self::ORDEN[$u->nivel] > self::ORDEN[$nivel->nivel]) {
                    $nivel = $u;
                }
            }

            $resultado[$alumnoId] = [
                'clases' => $d['clases'],
                'ausencias' => $d['ausencias'],
                'retardos' => $d['retardos'],
                'justificadas' => $d['justificadas'],
                'faltas' => $faltas,
                'porcentaje_inasistencia' => round($porcentaje, 2),
                'racha_dias' => $racha,
                'nivel_riesgo' => $nivel->nivel,
                'color_hex' => $nivel->color_hex,
            ];
        }

        return $resultado;
    }
}
