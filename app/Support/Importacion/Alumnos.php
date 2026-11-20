<?php

namespace App\Support\Importacion;

use App\Models\Alumno;
use App\Models\Grupo;
use App\Models\PadreTutor;
use App\Models\PeriodoEscolar;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Plantilla de alumnos + su tutor principal. Cada fila inscribe (o actualiza) a un alumno
 * en un grupo del periodo activo. Se identifica por matrícula: volver a subir el archivo
 * no duplica nada.
 */
class Alumnos
{
    /** Orden = columnas de la hoja Datos. */
    const COLUMNAS = [
        'Matrícula' => ['obligatoria' => true, 'ancho' => 18, 'texto' => true],
        'Apellido paterno' => ['obligatoria' => true],
        'Apellido materno' => [],
        'Nombre(s)' => ['obligatoria' => true, 'ancho' => 22],
        'Grupo' => ['obligatoria' => true, 'ancho' => 16],
        'Situación académica' => ['ancho' => 16],
        'Tutor: nombre completo' => ['ancho' => 32],
        'Tutor: parentesco' => ['ancho' => 14],
        'Tutor: teléfono' => ['ancho' => 16, 'texto' => true],
        'Tutor: correo' => ['ancho' => 28],
        'Tutor: canal de aviso' => ['ancho' => 16],
    ];

    const SITUACIONES = ['regular', 'irregular', 'recursador', 'extemporaneo'];

    const PARENTESCOS = ['Madre', 'Padre', 'Abuela', 'Abuelo', 'Tía', 'Tío', 'Tutor(a)', 'Otro'];

    public static function plantilla(): Spreadsheet
    {
        $periodo = PeriodoEscolar::activo();
        $libro = Plantilla::nueva('alumnos', ['Datos' => self::COLUMNAS], [
            'Plantilla de alumnos — SIGA',
            "Periodo: {$periodo?->nombre}. Una fila por alumno, en la hoja Datos, a partir de la fila 2.",
            '',
            'Columnas en azul: obligatorias. En gris: opcionales.',
            'Matrícula: tal como la usa el plantel. Si la matrícula ya existe, se actualizan sus datos y su grupo (no se duplica).',
            'Apellidos y nombre(s) van por separado; el sistema guarda "Apellido paterno Apellido materno Nombre(s)".',
            'Grupo: elígelo de la lista. Solo aparecen los grupos del periodo activo; si falta uno, dalo de alta en Catálogos y descarga la plantilla de nuevo.',
            'Situación académica: regular, irregular, recursador o extemporaneo. Vacía = regular.',
            '',
            'Tutor (opcional): si llenas cualquier dato del tutor, su nombre es obligatorio.',
            'Canal de aviso: sms necesita teléfono, email necesita correo. Vacío = sms si hay teléfono, si no email.',
            'Si dos hermanos tienen al mismo tutor, escribe el mismo nombre y el mismo teléfono (o correo): se registra una sola vez.',
            '',
            'Ejemplo de una fila:',
            '2607103500012 | Gómez | Pérez | Ana Sofía | 1A matutino | regular | Pérez López María | Madre | 961 123 4567 | | sms',
            '',
            'Si el archivo tiene errores no se importa nada: SIGA te dice la fila y la columna de cada error para que lo corrijas y lo vuelvas a subir.',
        ]);

        $grupos = self::grupos()->keys()->all();
        Plantilla::lista($libro, 5, $grupos, 'Elige un grupo de la lista.');
        Plantilla::lista($libro, 6, self::SITUACIONES, 'Elige regular, irregular, recursador o extemporaneo.');
        Plantilla::lista($libro, 8, self::PARENTESCOS, 'Elige un parentesco de la lista.');
        Plantilla::lista($libro, 11, ['sms', 'email'], 'Elige sms o email.');

        return $libro;
    }

    /**
     * Valida todo el archivo; si no hay errores, lo guarda. Todo o nada: con un solo error no se guarda ninguna fila.
     *
     * @return array{0: int, 1: list<array{fila: int, columna: ?string, mensaje: string}>} filas leídas y errores
     */
    public static function importar(Spreadsheet $libro): array
    {
        $filas = Plantilla::filas($libro, 'Datos', array_keys(self::COLUMNAS));
        $grupos = self::grupos()->mapWithKeys(fn ($id, $nombre) => [mb_strtolower($nombre) => $id]);
        $columnas = array_keys(self::COLUMNAS);
        $errores = [];
        $validas = [];
        $vistas = [];

        foreach ($filas as $n => $v) {
            $error = function (int $col, string $mensaje) use (&$errores, $n, $columnas) {
                $errores[] = ['fila' => $n, 'columna' => $columnas[$col], 'mensaje' => $mensaje];
            };
            [$matricula, $paterno, $materno, $nombres, $grupo, $situacion, $tNombre, $tParentesco, $tTelefono, $tCorreo, $tCanal] = array_pad($v, 11, '');

            if ($matricula === '') {
                $error(0, 'Falta la matrícula.');
            } elseif (mb_strlen($matricula) > 20) {
                $error(0, 'La matrícula tiene más de 20 caracteres.');
            } elseif (isset($vistas[$matricula])) {
                $error(0, "La matrícula {$matricula} ya está en la fila {$vistas[$matricula]}.");
            } else {
                $vistas[$matricula] = $n;
            }
            if ($paterno === '') {
                $error(1, 'Falta el apellido paterno.');
            }
            if ($nombres === '') {
                $error(3, 'Falta el nombre.');
            }
            $nombre = trim("{$paterno} {$materno} {$nombres}");
            if (mb_strlen($nombre) > 200) {
                $error(3, 'El nombre completo pasa de 200 caracteres.');
            }
            $grupoId = $grupos[mb_strtolower($grupo)] ?? null;
            if (! $grupoId) {
                $error(4, $grupo === '' ? 'Falta el grupo.' : "El grupo \"{$grupo}\" no existe en el periodo activo. Elígelo de la lista.");
            }
            $situacion = mb_strtolower($situacion) ?: 'regular';
            if (! in_array($situacion, self::SITUACIONES, true)) {
                $error(5, 'La situación debe ser regular, irregular, recursador o extemporaneo.');
            }

            $tutor = null;
            if ("{$tNombre}{$tParentesco}{$tTelefono}{$tCorreo}{$tCanal}" !== '') {
                $tutor = [
                    'nombre' => $tNombre,
                    'telefono' => $tTelefono ?: null,
                    'correo' => $tCorreo ?: null,
                    'canal_preferido' => mb_strtolower($tCanal) ?: ($tTelefono !== '' ? 'sms' : 'email'),
                ];
                $validador = Validator::make($tutor, PadreTutor::reglas(), [
                    'nombre.required' => 'Falta el nombre del tutor (lo pide porque llenaste otros datos del tutor).',
                    'canal_preferido.in' => 'El canal debe ser sms o email.',
                    'telefono.required_if' => 'Con canal sms se necesita el teléfono del tutor.',
                    'correo.required_if' => 'Con canal email se necesita el correo del tutor.',
                    'correo.email' => 'El correo del tutor no es válido.',
                    'correo.max' => 'El correo del tutor pasa de 160 caracteres.',
                    'telefono.regex' => 'El teléfono del tutor debe tener de 7 a 20 caracteres: números, espacios, +, paréntesis o guiones.',
                    'nombre.max' => 'El nombre del tutor pasa de 150 caracteres.',
                ]);
                foreach ($validador->errors()->messages() as $campo => $mensajes) {
                    $error(['nombre' => 6, 'telefono' => 8, 'correo' => 9, 'canal_preferido' => 10][$campo], $mensajes[0]);
                }
                if (mb_strlen($tParentesco) > 50) {
                    $error(7, 'El parentesco pasa de 50 caracteres.');
                }
            }

            $validas[] = compact('matricula', 'nombre', 'grupoId', 'situacion', 'tutor', 'tParentesco');
        }

        if ($errores) {
            return [count($filas), $errores];
        }

        // shortcut: unas 5 consultas por fila; con miles de alumnos tarda segundos, si pasa de ~5,000 filas mover a la cola
        DB::transaction(function () use ($validas) {
            foreach ($validas as $f) {
                $alumno = Alumno::firstOrNew(['matricula' => $f['matricula']]);
                $alumno->nombre = $f['nombre'];
                $alumno->activo ??= true; // si ya existía se respeta su baja
                $alumno->save();
                $alumno->inscribir(['grupo_id' => $f['grupoId'], 'situacion_academica' => $f['situacion']]);

                if ($f['tutor']) {
                    self::vincularTutor($alumno, $f['tutor'], $f['tParentesco'] ?: null);
                }
            }
        });

        return [count($filas), []];
    }

    /**
     * Mismo tutor = mismo nombre (sin importar mayúsculas) y mismo teléfono o correo: así dos hermanos
     * comparten al tutor y reimportar no lo duplica. El primero que tenga el alumno queda como principal.
     */
    private static function vincularTutor(Alumno $alumno, array $datos, ?string $parentesco): void
    {
        $tutor = $alumno->padresTutores()->whereRaw('LOWER(nombre) = ?', [mb_strtolower($datos['nombre'])])->first()
            ?? PadreTutor::whereRaw('LOWER(nombre) = ?', [mb_strtolower($datos['nombre'])])
                ->where(fn ($q) => $q
                    ->when($datos['telefono'], fn ($q, $t) => $q->orWhere('telefono', $t))
                    ->when($datos['correo'], fn ($q, $c) => $q->orWhere('correo', $c)))
                ->first()
            ?? new PadreTutor;
        $tutor->fill($datos)->save();

        $pivote = ['parentesco' => $parentesco];
        if ($alumno->padresTutores()->whereKey($tutor->id)->exists()) {
            $alumno->padresTutores()->updateExistingPivot($tutor->id, $pivote);
        } else {
            $sinPrincipal = ! $alumno->padresTutores()->wherePivot('es_principal', true)->exists();
            $alumno->padresTutores()->attach($tutor->id, $pivote + ['es_principal' => $sinPrincipal]);
        }
    }

    /** "1A matutino" => grupo_id, de los grupos del periodo activo, matutinos primero. */
    public static function grupos(): Collection
    {
        return Grupo::with('grado')
            ->where('periodo_id', PeriodoEscolar::activo()?->id)
            ->get()
            ->sortBy(fn (Grupo $g) => [$g->turno === 'vespertino', (int) $g->grado->nombre, $g->letra])
            ->mapWithKeys(fn (Grupo $g) => [self::nombreGrupo($g) => $g->id]);
    }

    public static function nombreGrupo(Grupo $g): string
    {
        return "{$g->grado->nombre}{$g->letra} {$g->turno}";
    }
}
