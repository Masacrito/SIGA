<?php

namespace App\Support\Importacion;

use App\Models\Calificacion;
use App\Models\Configuracion;
use App\Models\Grupo;
use App\Models\Horario;
use App\Models\Inscripcion;
use App\Models\Materia;
use App\Models\PeriodoEscolar;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Plantilla de calificaciones de un grupo en un parcial: ya trae la lista del grupo
 * (matrícula y nombre) y una columna por materia de su horario, con lo que ya está
 * capturado. Solo se llenan las calificaciones.
 */
class Calificaciones
{
    const PARCIALES = 3;

    public static function plantilla(Grupo $grupo, int $parcial): Spreadsheet
    {
        $periodoId = $grupo->periodo_id;
        $materias = self::materias($grupo);
        if ($materias->isEmpty()) {
            throw ValidationException::withMessages(['grupo_id' => 'El grupo no tiene materias en su horario. Agrégalas en Horarios.']);
        }
        $inscritos = Inscripcion::with('alumno')
            ->where('grupo_id', $grupo->id)->where('periodo_id', $periodoId)
            ->whereRelation('alumno', 'activo', true)
            ->get()->sortBy('alumno.nombre')->values();
        $previas = self::previas($inscritos->pluck('alumno_id'), $materias->keys(), $periodoId, $parcial);
        $escala = (int) Configuracion::valor('escala_calificacion', 10);
        $nombreGrupo = Alumnos::nombreGrupo($grupo);

        $libro = Plantilla::nueva('calificaciones', ['Datos' => self::columnas($materias)], [
            "Calificaciones del grupo {$nombreGrupo}, parcial {$parcial} — SIGA",
            'La hoja Datos ya trae a los alumnos del grupo y una columna por materia de su horario.',
            'No cambies las columnas Matrícula y Alumno, ni agregues, borres o muevas columnas.',
            "Materias numéricas: de 0 a {$escala}, con un decimal como máximo (8, 9.5). Materias de acreditación: A o NA (elígelo de la lista).",
            'Las calificaciones que ya estaban capturadas vienen escritas; si no cambias una, se queda igual.',
            'Una celda vacía no borra nada: deja la calificación como estaba.',
            'Si una calificación se corrige en SIGA después de descargar esta plantilla, SIGA lo marca como error para no pisarla: descarga la plantilla de nuevo.',
            '',
            'Si el archivo tiene errores no se importa nada: SIGA te dice la fila y la columna de cada error.',
        ], [
            'grupo_id' => $grupo->id,
            'periodo_id' => $periodoId,
            'parcial' => $parcial,
            'materias' => $materias->keys()->implode(','),
            'generada' => now()->toIso8601String(),
        ]);

        $hoja = $libro->getSheetByName('Datos');
        foreach ($inscritos as $i => $ins) {
            $fila = $i + 2;
            $hoja->setCellValueExplicit("A{$fila}", $ins->alumno->matricula, DataType::TYPE_STRING);
            $hoja->setCellValueExplicit("B{$fila}", $ins->alumno->nombre, DataType::TYPE_STRING);
            foreach ($materias->values() as $j => $m) {
                $valor = $previas->get("{$ins->alumno_id}-{$m->id}")?->calificacion;
                if ($valor !== null) {
                    $hoja->setCellValue(Coordinate::stringFromColumnIndex($j + 3).$fila, is_numeric($valor) ? (float) $valor : $valor);
                }
            }
        }
        $ultima = max(2, $inscritos->count() + 1);
        $hoja->getStyle("A2:B{$ultima}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EEF2F7');

        foreach ($materias->values() as $j => $m) {
            $col = $j + 3;
            if ($m->tipo_evaluacion === 'alfanumerica') {
                Plantilla::lista($libro, $col, ['A', 'NA'], 'Esta materia se califica con A (acreditada) o NA (no acreditada).');
            } else {
                $letra = Coordinate::stringFromColumnIndex($col);
                $hoja->getDataValidation("{$letra}2:{$letra}{$ultima}")
                    ->setType(DataValidation::TYPE_DECIMAL)
                    ->setOperator(DataValidation::OPERATOR_BETWEEN)
                    ->setFormula1('0')->setFormula2((string) $escala)
                    ->setAllowBlank(true)->setShowErrorMessage(true)->setErrorStyle(DataValidation::STYLE_STOP)
                    ->setErrorTitle('Calificación no válida')->setError("Escribe un número de 0 a {$escala}.");
            }
        }

        return $libro;
    }

    /**
     * Valida todo; si no hay errores, guarda. Todo o nada.
     *
     * @param  array<string, string>  $meta
     * @return array{0: int, 1: list<array{fila: int, columna: ?string, mensaje: string}>} filas leídas y errores
     */
    public static function importar(Spreadsheet $libro, array $meta): array
    {
        $periodoId = (int) ($meta['periodo_id'] ?? 0);
        $parcial = (int) ($meta['parcial'] ?? 0);
        $grupo = Grupo::find($meta['grupo_id'] ?? 0);
        if (! $grupo || $periodoId !== PeriodoEscolar::activo()?->id) {
            throw ValidationException::withMessages(['archivo' => 'La plantilla es de un grupo o periodo que ya no está activo. Descarga la plantilla de nuevo.']);
        }
        $ids = array_map('intval', explode(',', $meta['materias'] ?? ''));
        $materias = Materia::findMany($ids)->keyBy('id');
        if ($materias->count() !== count($ids)) {
            throw ValidationException::withMessages(['archivo' => Plantilla::COLUMNAS_CAMBIARON]);
        }
        $filas = Plantilla::filas($libro, 'Datos', array_keys(self::columnas(collect($ids)->map(fn ($id) => $materias[$id]))));

        $alumnoPorMatricula = Inscripcion::join('alumnos', 'alumnos.id', '=', 'inscripciones.alumno_id')
            ->where('grupo_id', $grupo->id)->where('periodo_id', $periodoId)
            ->pluck('inscripciones.alumno_id', 'alumnos.matricula');
        $previas = self::previas($alumnoPorMatricula->values(), $ids, $periodoId, $parcial);
        $generada = Carbon::parse($meta['generada'] ?? 'now');
        $nombreGrupo = Alumnos::nombreGrupo($grupo);

        $errores = [];
        $cambios = [];
        $vistas = [];
        foreach ($filas as $n => $v) {
            $matricula = $v[0] ?? '';
            $alumnoId = $alumnoPorMatricula[$matricula] ?? null;
            if (! $alumnoId) {
                $errores[] = ['fila' => $n, 'columna' => 'Matrícula', 'mensaje' => $matricula === ''
                    ? 'Falta la matrícula.'
                    : "La matrícula {$matricula} no está inscrita en {$nombreGrupo}."];

                continue;
            }
            if (isset($vistas[$matricula])) {
                $errores[] = ['fila' => $n, 'columna' => 'Matrícula', 'mensaje' => "La matrícula {$matricula} ya está en la fila {$vistas[$matricula]}."];

                continue;
            }
            $vistas[$matricula] = $n;

            foreach ($ids as $j => $materiaId) {
                $valor = $v[$j + 2] ?? '';
                if ($valor === '') {
                    continue; // shortcut: vaciar una celda no borra la calificación; si se pide borrar, agregarlo aquí
                }
                $materia = $materias[$materiaId];
                try {
                    $valor = Calificacion::normalizar($valor, $materia->tipo_evaluacion);
                } catch (\InvalidArgumentException $e) {
                    $errores[] = ['fila' => $n, 'columna' => $materia->nombre, 'mensaje' => $e->getMessage()];

                    continue;
                }

                $previa = $previas->get("{$alumnoId}-{$materiaId}");
                if ($previa?->calificacion === $valor) {
                    continue; // sin cambio: se respeta quién la capturó
                }
                if ($previa && $previa->updated_at?->greaterThan($generada)) {
                    $quien = $previa->editadoPor ? " ({$previa->editadoPor->nombre})" : '';
                    $errores[] = ['fila' => $n, 'columna' => $materia->nombre, 'mensaje' => "Se cambió en SIGA{$quien} después de descargar la plantilla; ahora vale {$previa->calificacion}. Descarga la plantilla de nuevo."];

                    continue;
                }
                $cambios[] = ['alumno_id' => $alumnoId, 'materia_id' => $materiaId, 'calificacion' => $valor];
            }
        }

        if ($errores) {
            return [count($filas), $errores];
        }

        DB::transaction(function () use ($cambios, $periodoId, $parcial) {
            foreach ($cambios as $c) {
                Calificacion::updateOrCreate(
                    ['alumno_id' => $c['alumno_id'], 'materia_id' => $c['materia_id'], 'periodo_id' => $periodoId, 'parcial' => $parcial, 'tipo' => 'regular'],
                    ['calificacion' => $c['calificacion'], 'origen' => 'importado', 'editado_por' => null],
                );
            }
        });

        return [count($filas), []];
    }

    /** Matrícula, Alumno y una columna por materia. */
    private static function columnas(Collection $materias): array
    {
        $columnas = ['Matrícula' => ['ancho' => 18, 'texto' => true], 'Alumno' => ['ancho' => 38]];
        foreach ($materias as $m) {
            $columnas["{$m->nombre} ({$m->clave})"] = ['obligatoria' => true, 'ancho' => 16];
        }

        return $columnas;
    }

    /** Materias del horario del grupo, por nombre, con su id como llave. */
    private static function materias(Grupo $grupo): Collection
    {
        return Materia::whereIn('id', Horario::where('grupo_id', $grupo->id)->select('materia_id'))
            ->orderBy('nombre')->get()->keyBy('id');
    }

    /** Calificaciones regulares ya capturadas, con llave "alumno-materia". */
    private static function previas($alumnoIds, $materiaIds, int $periodoId, int $parcial): Collection
    {
        return Calificacion::with('editadoPor:id,nombre')
            ->whereIn('alumno_id', $alumnoIds)->whereIn('materia_id', $materiaIds)
            ->where(['periodo_id' => $periodoId, 'parcial' => $parcial, 'tipo' => 'regular'])
            ->get()
            ->keyBy(fn (Calificacion $c) => "{$c->alumno_id}-{$c->materia_id}");
    }
}
