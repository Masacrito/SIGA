<?php

namespace App\Support\Importacion;

use App\Models\Docente;
use App\Models\Horario;
use App\Models\Materia;
use App\Models\PeriodoEscolar;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Plantilla de docentes y orientadores, con dos hojas:
 * - "Personal": una fila por persona. Se identifica por número de empleado: si ya existe
 *   se actualiza, si no se le crea cuenta con contraseña temporal. Para orientadores,
 *   los grupos que orienta.
 * - "Clases": una fila por persona + grupo + materia, con la hora de cada día. Es lo que
 *   liga a quien da la clase con su grupo (horarios). Los orientadores también dan clase.
 * Una celda vacía no borra nada (grupos del orientador, correo, clase de un día).
 */
class Docentes
{
    const PERSONAL = [
        'Número de empleado' => ['obligatoria' => true, 'ancho' => 16, 'texto' => true],
        'Apellido paterno' => ['obligatoria' => true],
        'Apellido materno' => [],
        'Nombre(s)' => ['obligatoria' => true, 'ancho' => 22],
        'Rol' => ['obligatoria' => true, 'ancho' => 14],
        'Correo' => ['ancho' => 28],
        'Grupos que orienta' => ['ancho' => 44],
    ];

    const DIAS = [1 => 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

    /** Sin 0/O, 1/l/I: la contraseña temporal se dicta o se copia a mano. */
    private const ALFABETO = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';

    private static function columnasClases(): array
    {
        return [
            'Número de empleado' => ['obligatoria' => true, 'ancho' => 16, 'texto' => true],
            'Grupo' => ['obligatoria' => true, 'ancho' => 16],
            'Materia' => ['obligatoria' => true, 'ancho' => 40],
        ] + array_fill_keys(self::DIAS, ['ancho' => 14, 'texto' => true]);
    }

    public static function plantilla(): Spreadsheet
    {
        $periodo = PeriodoEscolar::activo();
        $libro = Plantilla::nueva('docentes', ['Personal' => self::PERSONAL, 'Clases' => self::columnasClases()], [
            'Plantilla de docentes y orientadores — SIGA',
            "Periodo: {$periodo?->nombre}. Columnas en azul: obligatorias. En gris: opcionales. Una celda vacía no borra nada.",
            '',
            'HOJA PERSONAL: una fila por persona.',
            'Número de empleado: identifica a la persona. Si ya existe en SIGA se actualizan sus datos (no se duplica); si no, se le crea su cuenta.',
            'Rol: docente u orientador. Los orientadores también pueden tener clases en la hoja Clases.',
            'Grupos que orienta: solo para orientadores; los grupos que sigue en el semáforo, separados por coma y escritos como en la lista de la hoja Clases. Ejemplo: 1A matutino, 1B matutino',
            'Cuentas nuevas: el usuario se forma con iniciales + DOC u ORI + número de empleado (Luis Alberto Ruiz Vázquez, docente, empleado 01: LARVDOC01).',
            'Al terminar la importación SIGA muestra una sola vez el usuario y la contraseña temporal de cada cuenta nueva; cada quien la cambia al entrar por primera vez.',
            '',
            'HOJA CLASES: una fila por persona + grupo + materia.',
            'Número de empleado: de alguien de la hoja Personal o que ya exista en SIGA.',
            'Grupo y Materia: elígelos de la lista. Si falta alguno, dalo de alta en Catálogos y descarga la plantilla de nuevo.',
            'Lunes a Sábado: la hora de la clase ese día, como inicio-fin en 24 horas. Ejemplo: 07:00-07:50. Deja vacío el día sin clase.',
            'Si la clase ya existe en SIGA ese día, se actualizan su hora y quién la da. Las clases no se borran desde aquí: eso se hace en Horarios.',
            '',
            'Si el archivo tiene errores no se importa nada: SIGA te dice la hoja, la fila y la columna de cada error.',
        ], ['periodo_id' => $periodo?->id]);

        Plantilla::lista($libro, 5, Rol::FRENTE_A_GRUPO, 'Elige docente u orientador.', 'Personal');
        Plantilla::lista($libro, 2, Alumnos::grupos()->keys()->all(), 'Elige un grupo de la lista.', 'Clases');
        Plantilla::lista($libro, 3, self::materias()->keys()->all(), 'Elige una materia de la lista.', 'Clases');

        return $libro;
    }

    /**
     * Valida las dos hojas; si no hay errores, guarda. Todo o nada.
     *
     * @return array{0: int, 1: list<array{hoja: string, fila: int, columna: ?string, mensaje: string}>, 2: list<array<string, string>>}
     *                                                                                                                                   filas leídas, errores y credenciales de las cuentas nuevas
     */
    public static function importar(Spreadsheet $libro, array $meta): array
    {
        $periodoId = PeriodoEscolar::activo()?->id;
        if (! $periodoId || (int) ($meta['periodo_id'] ?? 0) !== $periodoId) {
            throw ValidationException::withMessages(['archivo' => 'La plantilla es de un periodo que ya no está activo. Descarga la plantilla de nuevo.']);
        }
        $filasPersonal = Plantilla::filas($libro, 'Personal', array_keys(self::PERSONAL));
        $filasClases = Plantilla::filas($libro, 'Clases', array_keys(self::columnasClases()));
        $total = count($filasPersonal) + count($filasClases);

        $grupos = Alumnos::grupos();
        $grupoPorNombre = $grupos->mapWithKeys(fn ($id, $nombre) => [mb_strtolower($nombre) => $id]);
        $materias = self::materias();
        $materiaPorNombre = $materias->mapWithKeys(fn ($id, $nombre) => [mb_strtolower($nombre) => $id]);
        $roles = Rol::whereIn('nombre', Rol::FRENTE_A_GRUPO)->pluck('id', 'nombre');

        $errores = [];
        $error = function (string $hoja, int $fila, string $columna, string $mensaje) use (&$errores) {
            $errores[] = compact('hoja', 'fila', 'columna', 'mensaje');
        };

        // Quién ya existe: por número de empleado, de las dos hojas
        $numeros = collect($filasPersonal)->merge($filasClases)->pluck(0)->filter()->unique();
        $existentes = Docente::with('usuario.rol')->whereIn('numero_empleado', $numeros)->get()->groupBy('numero_empleado');

        // --- Hoja Personal ---
        $personas = []; // número de empleado => datos
        foreach ($filasPersonal as $n => $v) {
            [$numero, $paterno, $materno, $nombres, $rol, $correo, $orienta] = array_pad($v, 7, '');
            $e = fn (string $columna, string $mensaje) => $error('Personal', $n, $columna, $mensaje);

            $numeroValido = false;
            if ($numero === '') {
                $e('Número de empleado', 'Falta el número de empleado.');
            } elseif (! preg_match('/^[A-Za-z0-9-]{1,20}$/', $numero)) {
                $e('Número de empleado', 'El número de empleado solo puede llevar letras, números y guiones (hasta 20).');
            } elseif (isset($personas[$numero])) {
                $e('Número de empleado', "El número de empleado {$numero} ya está en la fila {$personas[$numero]['fila']}.");
            } else {
                $numeroValido = true;
            }
            if ($paterno === '') {
                $e('Apellido paterno', 'Falta el apellido paterno.');
            }
            if ($nombres === '') {
                $e('Nombre(s)', 'Falta el nombre.');
            }
            $nombre = trim("{$paterno} {$materno} {$nombres}"); // apellidos primero, como los alumnos
            if (mb_strlen($nombre) > 150) {
                $e('Nombre(s)', 'El nombre completo pasa de 150 caracteres.');
            }
            $rol = mb_strtolower($rol);
            if (! isset($roles[$rol])) {
                $e('Rol', 'El rol debe ser docente u orientador.');
            }
            if ($correo !== '' && (! filter_var($correo, FILTER_VALIDATE_EMAIL) || mb_strlen($correo) > 160)) {
                $e('Correo', 'El correo no es válido.');
            }

            $orientaIds = [];
            foreach (array_filter(array_map('trim', preg_split('/[,;]/', $orienta))) as $g) {
                if ($id = $grupoPorNombre[mb_strtolower($g)] ?? null) {
                    $orientaIds[] = $id;
                } else {
                    $e('Grupos que orienta', "El grupo \"{$g}\" no existe en el periodo activo. Escríbelo como en la lista de la hoja Clases.");
                }
            }
            if ($orientaIds && $rol === 'docente') {
                $e('Grupos que orienta', 'Solo los orientadores tienen grupos que orientar. Deja vacía esta columna o cambia el rol.');
            }

            if (! $numeroValido) {
                continue;
            }
            $usuario = self::existente($existentes, $numero, fn ($m) => $e('Número de empleado', $m));
            $personas[$numero] = [
                'fila' => $n, 'nombre' => $nombre, 'rol' => $rol, 'correo' => $correo, 'orienta' => $orientaIds, 'usuario' => $usuario,
                'username' => $usuario ? null : self::username($nombres, $paterno, $materno, $rol, $numero),
            ];
        }

        // Los usuarios nuevos no deben chocar con cuentas que ya existen (dadas de alta a mano)
        $ocupados = Usuario::whereIn('username', array_filter(array_column($personas, 'username')))->pluck('username')
            ->map(fn ($u) => mb_strtolower($u))->flip();
        foreach ($personas as $p) {
            if ($p['username'] && isset($ocupados[mb_strtolower($p['username'])])) {
                $error('Personal', $p['fila'], 'Número de empleado', "Su usuario sería {$p['username']}, que ya existe con otra persona. Si es la misma, ponle este número de empleado en Usuarios.");
            }
        }

        // --- Hoja Clases ---
        $columnasClases = array_keys(self::columnasClases());
        $clases = []; // lo que pide el archivo: una entrada por fila y día
        $vistas = [];
        foreach ($filasClases as $n => $v) {
            $v = array_pad($v, count($columnasClases), '');
            [$numero, $grupo, $materia] = $v;
            $e = fn (string $columna, string $mensaje) => $error('Clases', $n, $columna, $mensaje);

            if ($numero === '') {
                $e('Número de empleado', 'Falta el número de empleado.');
            } elseif (! isset($personas[$numero]) && ! $existentes->has($numero)) {
                $e('Número de empleado', "El número de empleado {$numero} no está en la hoja Personal ni existe en SIGA.");
            } elseif (! isset($personas[$numero])) {
                self::existente($existentes, $numero, fn ($m) => $e('Número de empleado', $m));
            }
            $grupoId = $grupoPorNombre[mb_strtolower($grupo)] ?? null;
            if (! $grupoId) {
                $e('Grupo', $grupo === '' ? 'Falta el grupo.' : "El grupo \"{$grupo}\" no existe en el periodo activo. Elígelo de la lista.");
            }
            $materiaId = $materiaPorNombre[mb_strtolower($materia)] ?? null;
            if (! $materiaId) {
                $e('Materia', $materia === '' ? 'Falta la materia.' : "La materia \"{$materia}\" no existe. Elígela de la lista.");
            }

            $conHora = 0;
            foreach (self::DIAS as $dia => $nombreDia) {
                $celda = $v[$dia + 2];
                if ($celda === '') {
                    continue;
                }
                $conHora++;
                if (! $horas = self::horas($celda)) {
                    $e($nombreDia, "\"{$celda}\" no es un horario. Escríbelo como inicio-fin en 24 horas, por ejemplo 07:00-07:50.");

                    continue;
                }
                if (! $grupoId || ! $materiaId) {
                    continue;
                }
                $llave = "{$grupoId}-{$materiaId}-{$dia}";
                if (isset($vistas[$llave])) {
                    $e($nombreDia, "Esta clase del {$nombreDia} ya está en la fila {$vistas[$llave]}.");

                    continue;
                }
                $vistas[$llave] = $n;
                $clases[] = ['fila' => $n, 'numero' => $numero, 'grupo_id' => $grupoId, 'materia_id' => $materiaId, 'dia' => $dia, 'ini' => $horas[0], 'fin' => $horas[1], 'id' => null];
            }
            if (! $conHora) {
                $e('Lunes', 'Escribe la hora de la clase en al menos un día.');
            }
        }

        // --- Choques: ni el grupo ni quien da la clase pueden estar en dos clases a la vez ---
        // Se revisa contra cómo quedaría el horario: lo que ya existe, con lo del archivo encima.
        $actuales = Horario::with('docente')->whereIn('grupo_id', $grupos->values())->get()
            ->map(fn (Horario $h) => [
                'fila' => null, 'numero' => $h->docente->numero_empleado, 'docente_id' => $h->docente_id, 'grupo_id' => $h->grupo_id, 'materia_id' => $h->materia_id,
                'dia' => $h->dia_semana, 'ini' => substr($h->hora_inicio, 0, 5), 'fin' => substr($h->hora_fin, 0, 5), 'id' => $h->id,
            ])->keyBy('id');
        foreach ($clases as &$c) {
            $mismas = $actuales->where('grupo_id', $c['grupo_id'])->where('materia_id', $c['materia_id'])->where('dia', $c['dia']);
            if ($mismas->count() > 1) {
                $error('Clases', $c['fila'], self::DIAS[$c['dia']], 'Esta materia ya tiene más de una clase ese día en SIGA. Ajústala en la pantalla Horarios.');
            } elseif ($mismas->count() === 1) {
                $c['id'] = $mismas->keys()->first(); // se actualiza esa clase
                $actuales->forget($c['id']);
            }
            $c['docente_id'] = isset($personas[$c['numero']])
                ? $personas[$c['numero']]['usuario']?->docente?->id
                : $existentes->get($c['numero'])?->first()->id;
        }
        unset($c);

        $nombreMateria = $materias->flip();
        $nombreGrupo = $grupos->flip();
        $mismoDocente = fn ($a, $b) => ($a['docente_id'] && $a['docente_id'] === $b['docente_id']) || ((string) $a['numero'] !== '' && $a['numero'] === $b['numero']);
        $final = $actuales->values()->merge($clases);
        foreach ($clases as $c) {
            foreach ($final as $o) {
                if ($o === $c || $o['dia'] !== $c['dia'] || $o['ini'] >= $c['fin'] || $o['fin'] <= $c['ini']) {
                    continue;
                }
                $otra = sprintf('%s (%s–%s)%s', $nombreMateria[$o['materia_id']] ?? 'otra clase', $o['ini'], $o['fin'], $o['fila'] ? ", fila {$o['fila']}" : ', ya en SIGA');
                if ($o['grupo_id'] === $c['grupo_id']) {
                    $error('Clases', $c['fila'], self::DIAS[$c['dia']], "El grupo ya tiene {$otra} a esa hora.");
                } elseif ($mismoDocente($c, $o)) {
                    $error('Clases', $c['fila'], self::DIAS[$c['dia']], "Quien da esta clase ya da {$otra} en {$nombreGrupo[$o['grupo_id']]} a esa hora.");
                }
            }
        }

        if ($errores) {
            return [$total, $errores, []];
        }

        $credenciales = DB::transaction(function () use ($personas, $clases, $roles, $grupos) {
            $credenciales = [];
            $docenteId = [];
            foreach ($personas as $numero => $p) {
                $usuario = $p['usuario'];
                $datos = ['nombre' => $p['nombre'], 'rol_id' => $roles[$p['rol']]] + ($p['correo'] !== '' ? ['correo' => $p['correo']] : []);
                if ($usuario) {
                    $usuario->update($datos);
                } else {
                    $password = self::passwordTemporal();
                    $usuario = new Usuario($datos + ['username' => $p['username'], 'password' => $password]);
                    $usuario->forceFill(['debe_cambiar_password' => true])->save();
                    $credenciales[] = ['numero_empleado' => (string) $numero, 'nombre' => $p['nombre'], 'rol' => $p['rol'], 'usuario' => $p['username'], 'password' => $password];
                }
                $usuario->sincronizarRol(['numero_empleado' => (string) $numero]);
                if ($p['orienta']) {
                    // La lista manda sobre sus grupos de este periodo; los de periodos anteriores no se tocan
                    $usuario->gruposOrientados()->detach($grupos->values()->diff($p['orienta']));
                    $usuario->gruposOrientados()->syncWithoutDetaching($p['orienta']);
                }
                $docenteId[$numero] = $usuario->docente()->value('id');
            }

            foreach ($clases as $c) {
                $datos = [
                    'docente_id' => $docenteId[$c['numero']] ?? $c['docente_id'],
                    'hora_inicio' => "{$c['ini']}:00",
                    'hora_fin' => "{$c['fin']}:00",
                ];
                $c['id']
                    ? Horario::whereKey($c['id'])->update($datos)
                    : Horario::create($datos + ['grupo_id' => $c['grupo_id'], 'materia_id' => $c['materia_id'], 'dia_semana' => $c['dia']]);
            }

            return $credenciales;
        });

        return [$total, [], $credenciales];
    }

    /**
     * La cuenta que ya tiene ese número de empleado, o null si es nueva. La importación solo
     * toca cuentas de docente u orientador: nunca cambia una de administración.
     */
    private static function existente($existentes, string $numero, \Closure $error): ?Usuario
    {
        $filas = $existentes[$numero] ?? null;
        if (! $filas) {
            return null;
        }
        $usuario = $filas->first()->usuario;
        if ($filas->count() > 1) {
            $error("Hay {$filas->count()} cuentas con el número de empleado {$numero}. Corrígelo en Usuarios.");
        } elseif ($usuario->es_super_admin || ! in_array($usuario->rol?->nombre, Rol::FRENTE_A_GRUPO, true)) {
            $error("El número de empleado {$numero} es de una cuenta de administración ({$usuario->username}); esas no se cambian por importación.");
        } else {
            return $usuario;
        }

        return null;
    }

    /** Iniciales + DOC/ORI + número de empleado: Luis Alberto Ruiz Vázquez, docente, 01 → LARVDOC01. */
    private static function username(string $nombres, string $paterno, string $materno, string $rol, string $numero): string
    {
        $iniciales = collect(preg_split('/\s+/', Str::ascii("{$nombres} {$paterno} {$materno}"), -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn ($palabra) => $palabra[0])->implode('');

        return strtoupper($iniciales.($rol === 'orientador' ? 'ORI' : 'DOC').$numero);
    }

    private static function passwordTemporal(): string
    {
        return implode('', array_map(fn () => self::ALFABETO[random_int(0, strlen(self::ALFABETO) - 1)], range(1, 10)));
    }

    /** "7:00 - 7:50" → ['07:00', '07:50']; null si no es un horario válido (fin después de inicio). */
    private static function horas(string $celda): ?array
    {
        if (! preg_match('/^(\d{1,2}):(\d{2})\s*(?:-|–|a)\s*(\d{1,2}):(\d{2})$/u', $celda, $m) || $m[1] > 23 || $m[3] > 23 || $m[2] > 59 || $m[4] > 59) {
            return null;
        }
        $ini = sprintf('%02d:%02d', $m[1], $m[2]);
        $fin = sprintf('%02d:%02d', $m[3], $m[4]);

        return $fin > $ini ? [$ini, $fin] : null;
    }

    /** "Nombre (CLAVE)" => materia_id, por nombre. */
    private static function materias()
    {
        return Materia::orderBy('nombre')->get()->mapWithKeys(fn (Materia $m) => ["{$m->nombre} ({$m->clave})" => $m->id]);
    }
}
