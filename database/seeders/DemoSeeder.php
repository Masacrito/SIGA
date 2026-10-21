<?php

namespace Database\Seeders;

use App\Models\Alumno;
use App\Models\Asistencia;
use App\Models\Docente;
use App\Models\Grado;
use App\Models\Grupo;
use App\Models\Importacion;
use App\Models\Materia;
use App\Models\PeriodoEscolar;
use App\Models\Plantel;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Demo del prototipo (solo local): un plantel creíble para enseñar el sistema.
 *
 * Periodo 2026 B (agosto–enero) → solo semestres nones: 1°, 3° y 5°.
 * Por semestre, grupos A–C matutino y D–G vespertino, 15 alumnos cada uno,
 * un tutor por alumno: 21 grupos, 315 alumnos. 6 materias por semestre, un
 * docente por materia (da en ambos turnos), 2 orientadores (uno por turno) y
 * las últimas 4 semanas hábiles de clases (~38 mil asistencias).
 * Nombres con apellidos primero, como en las listas escolares.
 *
 * Determinista (semilla fija): siempre salen los mismos datos.
 * Casos especiales, todos en 1° A (ver casosEspeciales()).
 * Las pruebas automáticas NO usan esto: usan PruebasSeeder.
 */
class DemoSeeder extends Seeder
{
    /** Para la app móvil: reenviarlo debe dar "duplicado" (alumno 1 de 1° A, última clase). */
    public const UUID_DUPLICADO = '00000000-0000-4000-8000-000000000001';

    /** Alumno 2 de 1° A ya tiene registro en la última clase: un uuid nuevo debe dar "conflicto". */
    public const UUID_CONFLICTO_EXISTENTE = '00000000-0000-4000-8000-000000000002';

    private const PASSWORD = 'password';

    private const ALUMNOS_POR_GRUPO = 15;

    private const DIAS_HABILES = 20;

    private const TURNOS = ['matutino' => ['A', 'B', 'C'], 'vespertino' => ['D', 'E', 'F', 'G']];

    private const HORAS = [
        'matutino' => [['07:00', '07:50'], ['07:50', '08:40'], ['08:40', '09:30'], ['10:00', '10:50'], ['10:50', '11:40'], ['11:40', '12:30']],
        'vespertino' => [['13:30', '14:20'], ['14:20', '15:10'], ['15:10', '16:00'], ['16:30', '17:20'], ['17:20', '18:10'], ['18:10', '19:00']],
    ];

    /** Semestre → [clave, nombre, tipo_evaluacion]; un docente por materia. */
    private const MATERIAS = [
        '1' => [['MAT-101', 'Matemáticas I'], ['QUI-101', 'Química I'], ['TLR-101', 'Taller de Lectura y Redacción I'], ['ING-101', 'Inglés I'], ['INF-101', 'Informática I'], ['ETI-101', 'Ética I']],
        '3' => [['MAT-301', 'Matemáticas III'], ['BIO-301', 'Biología I'], ['FIS-301', 'Física I'], ['ING-301', 'Inglés III'], ['LIT-301', 'Literatura I'], ['HMX-301', 'Historia de México II']],
        '5' => [['CAL-501', 'Cálculo Diferencial'], ['GEO-501', 'Geografía'], ['ESM-501', 'Estructura Socioeconómica de México'], ['ECO-501', 'Ecología y Medio Ambiente'], ['ING-501', 'Inglés V'], ['ORI-501', 'Orientación Educativa V', 'alfanumerica']],
    ];

    private const NOMBRES_H = ['José Luis', 'Juan Carlos', 'Luis Ángel', 'Miguel Ángel', 'José Antonio', 'Jesús', 'Carlos Eduardo', 'Diego', 'Santiago', 'Emiliano', 'Sebastián', 'Leonardo', 'Mateo', 'Daniel', 'Alejandro', 'Ángel Gabriel', 'Kevin', 'Brayan', 'Jorge Armando', 'Óscar', 'Fernando', 'Ricardo', 'Eduardo', 'Iván', 'Christian', 'Abraham', 'Isaac', 'Rodrigo', 'Héctor', 'Manuel', 'Francisco Javier', 'Gael', 'Alexis', 'Josué', 'Erick', 'Uriel', 'Martín', 'Rafael', 'Adrián', 'Julio César'];

    private const NOMBRES_M = ['María Fernanda', 'Guadalupe', 'Ximena', 'Valeria', 'Daniela', 'Andrea', 'Camila', 'Sofía', 'Regina', 'Itzel', 'Karla', 'Fernanda', 'Alejandra', 'Paola', 'Natalia', 'Ana Karen', 'Diana Laura', 'Yuliana', 'Mariana', 'Brenda', 'Dulce María', 'Estefanía', 'Jimena', 'Lizbeth', 'Montserrat', 'Areli', 'Citlali', 'Nayeli', 'Rosa Isela', 'Wendy', 'Abigail', 'Michelle', 'Gabriela', 'Vanessa', 'Alondra', 'Leslie', 'Joselyn', 'Karina', 'Elena', 'Lucía'];

    private const ADULTOS_H = ['Juan', 'José', 'Francisco', 'Pedro', 'Manuel', 'Jorge', 'Roberto', 'Arturo', 'Alfredo', 'Mario', 'Raúl', 'Sergio', 'Javier', 'Armando', 'Enrique', 'Rubén', 'Gerardo', 'Víctor Manuel', 'Ernesto', 'Alberto', 'Ramiro', 'Hugo', 'Filiberto', 'Rigoberto'];

    private const ADULTOS_M = ['María Elena', 'Rosa María', 'Guadalupe', 'Martha Patricia', 'Leticia', 'Verónica', 'Claudia', 'Silvia', 'Laura', 'Patricia', 'Norma Angélica', 'Araceli', 'Maribel', 'Rocío', 'Gloria', 'Juana', 'Margarita', 'Elizabeth', 'Adriana', 'Teresa', 'Sandra Luz', 'Yolanda', 'Irma', 'Carmen'];

    /** Los más comunes en México, con varios de Chiapas (Coutiño, Zenteno, Grajales, Albores…). */
    private const APELLIDOS = ['Hernández', 'García', 'Martínez', 'López', 'González', 'Pérez', 'Rodríguez', 'Sánchez', 'Ramírez', 'Cruz', 'Gómez', 'Díaz', 'Ruiz', 'Velázquez', 'Morales', 'Jiménez', 'Vázquez', 'Gutiérrez', 'Moreno', 'Méndez', 'Aguilar', 'Castillo', 'Ortiz', 'Torres', 'Reyes', 'Flores', 'Domínguez', 'Santiago', 'Álvarez', 'Mendoza', 'Nájera', 'Coutiño', 'Solís', 'Zenteno', 'Grajales', 'Albores', 'Culebro', 'Penagos', 'Estrada', 'Toledo', 'Gordillo', 'Trujillo', 'Cancino', 'Moguel', 'Escobar'];

    private const MOTIVOS_JUSTIFICANTE = ['Consulta médica (constancia del IMSS)', 'Cita en el centro de salud', 'Defunción de un familiar', 'Trámite oficial con su tutor', 'Incapacidad médica'];

    private PeriodoEscolar $periodo;

    /** bcrypt de PASSWORD, calculado una vez para los 21 usuarios */
    private string $hash;

    /** @var array<string, Usuario> */
    private array $usuarios = [];

    /** @var array<string, int> "semestre:indiceMateria" → docente_id */
    private array $docentes = [];

    /** @var array<string, int> docente_id → usuario_id */
    private array $usuarioDeDocente = [];

    /** @var array<string, array{grupo: Grupo, turno: string, semestre: string}> "1A" → grupo */
    private array $grupos = [];

    /** @var array<string, Materia[]> semestre → materias en orden */
    private array $materias = [];

    /** @var array<string, array<int, array{alumno: Alumno, perfil: string}>> "1A" → lista 1..15 */
    private array $alumnos = [];

    /** @var int[] alumnos con historial armado a mano: no se les toca nada después */
    private array $intactos = [];

    /** @var array<string, string> usernames ya usados */
    private array $usernames = [];

    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->error('DemoSeeder es solo para local (el demo del prototipo).');

            return;
        }
        // Solo sobre una base limpia: cualquier usuario además del súper admin
        // (de este demo o de uno anterior) chocaría con los usernames del demo
        if (Usuario::where('es_super_admin', false)->exists()) {
            $this->command?->warn('Ya hay datos de un demo; usa migrate:fresh --seed para regenerarlo.');

            return;
        }

        mt_srand(35);
        $this->periodo = PeriodoEscolar::activo();
        $this->hash = Hash::make(self::PASSWORD);

        DB::transaction(function () {
            $this->personal();
            $this->gruposYMaterias();
            $this->horarios();
            $this->alumnosYTutores();
            $this->asistencias();
            $this->calificaciones();
            $this->seguimiento();
        });

        $this->command?->info('Demo listo: 21 grupos, 315 alumnos. Usuarios: admin, orientador.matutino, orientador.vespertino y los docentes (contraseña: '.self::PASSWORD.').');
    }

    // ---------------------------------------------------------------- personal

    private function personal(): void
    {
        $this->usuarios['admin'] = $this->usuario('admin', 'administrador', 'Administración del Plantel');
        $this->usuarios['orientador.matutino'] = $this->usuario('orientador.matutino', 'orientador', 'Gómez Coutiño Martha Patricia');
        $this->usuarios['orientador.vespertino'] = $this->usuario('orientador.vespertino', 'orientador', 'Nájera Solís Jorge Alberto');

        // Un docente por materia de cada semestre; da esa materia en todos los grupos de ambos turnos
        $n = 0;
        foreach (self::MATERIAS as $semestre => $lista) {
            foreach ($lista as $i => $_) {
                $n++;
                $mujer = $n % 2 === 0;
                [$paterno, $materno] = $this->dosApellidos();
                $nombre = $this->elegir($mujer ? self::ADULTOS_M : self::ADULTOS_H);
                $usuario = $this->usuario($this->username($nombre, $paterno), 'docente', "{$paterno} {$materno} {$nombre}");
                $docente = Docente::create(['usuario_id' => $usuario->id, 'numero_empleado' => sprintf('D-%04d', 1000 + $n)]);
                $this->docentes["{$semestre}:{$i}"] = $docente->id;
                $this->usuarioDeDocente[$docente->id] = $usuario->id;
            }
        }
    }

    private function usuario(string $username, string $rol, string $nombre): Usuario
    {
        $this->usernames[$username] = $username;

        return Usuario::create([
            'rol_id' => Rol::where('nombre', $rol)->value('id'),
            'nombre' => $nombre,
            'username' => $username,
            'password' => $this->hash, // ya cifrado: el cast 'hashed' no lo vuelve a cifrar
            'correo' => "{$username}@cobach35.edu.mx",
            'activo' => true,
        ]);
    }

    /** jhernandez, jhernandez2… */
    private function username(string $nombre, string $paterno): string
    {
        $base = Str::lower(Str::ascii(mb_substr($nombre, 0, 1).$paterno));
        $u = $base;
        for ($k = 2; isset($this->usernames[$u]); $k++) {
            $u = $base.$k;
        }

        return $u;
    }

    // ----------------------------------------------------- grupos y materias

    private function gruposYMaterias(): void
    {
        $plantel = Plantel::first();

        foreach (self::MATERIAS as $semestre => $lista) {
            $gradoId = Grado::where('nombre', $semestre)->value('id');

            foreach ($lista as $m) {
                $this->materias[$semestre][] = Materia::create(['clave' => $m[0], 'nombre' => $m[1], 'tipo_evaluacion' => $m[2] ?? 'numerica']);
            }

            foreach (self::TURNOS as $turno => $letras) {
                foreach ($letras as $letra) {
                    $grupo = Grupo::create([
                        'grado_id' => $gradoId,
                        'plantel_id' => $plantel->id,
                        'periodo_id' => $this->periodo->id,
                        'letra' => $letra,
                        'turno' => $turno,
                    ]);
                    $this->grupos[$semestre.$letra] = ['grupo' => $grupo, 'turno' => $turno, 'semestre' => $semestre];
                    $this->usuarios["orientador.{$turno}"]->gruposOrientados()->attach($grupo->id);
                }
            }
        }
    }

    /**
     * Cada materia, todos los días (6 clases diarias). La hora rota por grupo
     * y por día: así un docente nunca tiene dos grupos a la misma hora.
     */
    private function horarios(): void
    {
        $filas = [];
        foreach ($this->grupos as $g) {
            $indiceGrupo = array_search($g['grupo']->letra, self::TURNOS[$g['turno']], true);
            foreach ($this->materias[$g['semestre']] as $i => $materia) {
                for ($dia = 1; $dia <= 5; $dia++) {
                    [$inicio, $fin] = self::HORAS[$g['turno']][($i + $indiceGrupo + $dia) % 6];
                    $filas[] = [
                        'grupo_id' => $g['grupo']->id,
                        'materia_id' => $materia->id,
                        'docente_id' => $this->docentes["{$g['semestre']}:{$i}"],
                        'dia_semana' => $dia,
                        'hora_inicio' => $inicio,
                        'hora_fin' => $fin,
                    ];
                }
            }
        }
        DB::table('horarios')->insert($filas);
    }

    // --------------------------------------------------- alumnos y tutores

    private function alumnosYTutores(): void
    {
        $ingreso = ['1' => 26, '3' => 25, '5' => 24];
        $consecutivo = 0;
        $ahora = now();

        foreach ($this->grupos as $clave => $g) {
            for ($pos = 1; $pos <= self::ALUMNOS_POR_GRUPO; $pos++) {
                $consecutivo++;
                $mujer = mt_rand(0, 1) === 1;
                [$paterno, $materno] = $this->dosApellidos();
                $nombre = $this->elegir($mujer ? self::NOMBRES_M : self::NOMBRES_H);

                $alumno = Alumno::create([
                    'matricula' => sprintf('%02d0350%04d', $ingreso[$g['semestre']], $consecutivo),
                    'nombre' => "{$paterno} {$materno} {$nombre}",
                    'activo' => true,
                ]);

                $situacion = match (true) {
                    $g['semestre'] === '1' => $pos === 15 ? 'extemporaneo' : 'regular',
                    mt_rand(1, 100) <= 8 => 'irregular',
                    mt_rand(1, 100) <= 3 => 'recursador',
                    default => 'regular',
                };
                DB::table('inscripciones')->insert([
                    'alumno_id' => $alumno->id,
                    'grupo_id' => $g['grupo']->id,
                    'periodo_id' => $this->periodo->id,
                    'situacion_academica' => $situacion,
                    'fecha_inscripcion' => $this->periodo->fecha_inicio,
                ]);

                // Tutor: la madre (lleva el apellido materno del alumno) o el padre (el paterno)
                $esMadre = mt_rand(1, 100) <= 65;
                $nombreTutor = $esMadre
                    ? $materno.' '.$this->elegir(self::APELLIDOS).' '.$this->elegir(self::ADULTOS_M)
                    : $paterno.' '.$this->elegir(self::APELLIDOS).' '.$this->elegir(self::ADULTOS_H);
                $porCorreo = mt_rand(1, 100) <= 20;
                $tutorId = DB::table('padres_tutores')->insertGetId([
                    'nombre' => $nombreTutor,
                    'telefono' => '961 '.mt_rand(100, 999).' '.mt_rand(1000, 9999),
                    'correo' => $porCorreo || mt_rand(1, 100) <= 30 ? $this->correo($nombreTutor) : null,
                    'canal_preferido' => $porCorreo ? 'email' : 'sms',
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
                $alumno->padresTutores()->attach($tutorId, ['parentesco' => $esMadre ? 'madre' : 'padre', 'es_principal' => true]);

                // Perfil de asistencia: la mayoría cumple; unos cuantos van en riesgo
                $r = mt_rand(1, 100);
                $perfil = $r <= 65 ? 'cumplido' : ($r <= 85 ? 'medio' : 'riesgo');
                $this->alumnos[$clave][$pos] = ['alumno' => $alumno, 'perfil' => $perfil];
            }
        }
    }

    private function correo(string $nombreCompleto): string
    {
        $partes = explode(' ', Str::lower(Str::ascii($nombreCompleto)));
        $dominios = ['gmail.com', 'hotmail.com', 'outlook.com'];

        return $partes[2].'.'.$partes[0].mt_rand(10, 99).'@'.$dominios[mt_rand(0, 2)];
    }

    // ------------------------------------------------------------ asistencias

    /** Los últimos 20 días hábiles antes de hoy, del más viejo al más reciente. */
    private function diasHabiles(): array
    {
        $dias = [];
        for ($f = Carbon::yesterday(); count($dias) < self::DIAS_HABILES; $f->subDay()) {
            if ($f->isWeekday()) {
                $dias[] = $f->copy();
            }
        }

        return array_reverse($dias);
    }

    private function asistencias(): void
    {
        $dias = $this->diasHabiles();
        $horarios = DB::table('horarios')->get()->groupBy(fn ($h) => $h->grupo_id.':'.$h->dia_semana);

        // 1) Sesiones de clase: cada horario en cada día hábil que le toca
        $sesiones = [];
        foreach ($this->grupos as $g) {
            foreach ($dias as $d) {
                foreach ($horarios[$g['grupo']->id.':'.$d->dayOfWeekIso] ?? [] as $h) {
                    $sesiones[] = ['horario_id' => $h->id, 'fecha' => $d->toDateString(), 'cancelada' => false, 'motivo_cancelacion' => null];
                }
            }
        }
        foreach (array_chunk($sesiones, 1000) as $lote) {
            DB::table('sesiones_clase')->insert($lote);
        }

        // Una suspensión: 3° D, primer día, primeras dos horas (no llevan asistencias)
        $primerDia = $dias[0]->toDateString();
        $suspendidas = DB::table('sesiones_clase as s')->join('horarios as h', 'h.id', '=', 's.horario_id')
            ->where('h.grupo_id', $this->grupos['3D']['grupo']->id)->where('s.fecha', $primerDia)
            ->orderBy('h.hora_inicio')->limit(2)->pluck('s.id');
        DB::table('sesiones_clase')->whereIn('id', $suspendidas)->update(['cancelada' => true, 'motivo_cancelacion' => 'Junta de academia']);

        // 2) Por grupo, sus clases en orden cronológico: [fecha => [[sesion_id, hora, usuario_id]…]]
        $clasesPorGrupo = [];
        DB::table('sesiones_clase as s')->join('horarios as h', 'h.id', '=', 's.horario_id')
            ->where('s.cancelada', false)
            ->orderBy('s.fecha')->orderBy('h.hora_inicio')
            ->get(['s.id', 's.fecha', 'h.grupo_id', 'h.hora_inicio', 'h.docente_id'])
            ->each(function ($c) use (&$clasesPorGrupo) {
                $clasesPorGrupo[$c->grupo_id][substr($c->fecha, 0, 10)][] = [$c->id, $c->hora_inicio, $this->usuarioDeDocente[$c->docente_id]];
            });

        $especiales = $this->casosEspeciales($clasesPorGrupo);

        // 3) Asistencias: la falta suele ser de día completo (si no llega, falta a todas)
        $filas = [];
        foreach ($this->alumnos as $clave => $lista) {
            $grupoId = $this->grupos[$clave]['grupo']->id;
            foreach ($lista as $pos => ['alumno' => $alumno, 'perfil' => $perfil]) {
                $estados = $especiales[$alumno->id] ?? null;
                if ($estados !== null) {
                    foreach ($estados as [$sesionId, $hora, $usuarioId, $fecha, $estado]) {
                        $filas[] = $this->fila($sesionId, $alumno->id, $usuarioId, $fecha, $hora, $estado);
                    }

                    continue;
                }

                [$pDia, $pRetardo] = ['cumplido' => [3, 3], 'medio' => [12, 6], 'riesgo' => [26, 9]][$perfil];
                foreach ($clasesPorGrupo[$grupoId] as $fecha => $clases) {
                    $faltaElDia = mt_rand(1, 100) <= $pDia;
                    foreach ($clases as [$sesionId, $hora, $usuarioId]) {
                        $estado = match (true) {
                            $faltaElDia => 'ausente',
                            mt_rand(1, 100) <= 1 => 'ausente',
                            mt_rand(1, 100) <= $pRetardo => 'retardo',
                            default => 'presente',
                        };
                        $filas[] = $this->fila($sesionId, $alumno->id, $usuarioId, $fecha, $hora, $estado);
                    }
                }
            }
        }

        foreach (array_chunk($filas, 1000) as $lote) {
            DB::table('asistencias')->insert($lote);
        }
        $this->justificaciones();

        // Casos fijos para probar la sincronización offline desde la app (1° A, última clase)
        $ultimaClase = collect($clasesPorGrupo[$this->grupos['1A']['grupo']->id])->last();
        $ultimaSesion = end($ultimaClase)[0];
        foreach ([1 => self::UUID_DUPLICADO, 2 => self::UUID_CONFLICTO_EXISTENTE] as $pos => $uuid) {
            Asistencia::where('sesion_clase_id', $ultimaSesion)->where('alumno_id', $this->alumnos['1A'][$pos]['alumno']->id)
                ->update(['uuid_local' => $uuid, 'origen' => 'offline', 'sincronizada_en' => now()]);
        }
    }

    private function fila(int $sesionId, int $alumnoId, int $usuarioId, string $fecha, string $hora, string $estado): array
    {
        $capturada = Carbon::parse("{$fecha} {$hora}")->addMinutes(mt_rand(2, 9));
        $offline = mt_rand(1, 100) <= 20; // 1 de cada 5 listas se pasó sin señal

        return [
            'sesion_clase_id' => $sesionId,
            'alumno_id' => $alumnoId,
            'registrado_por' => $usuarioId,
            'estado' => $estado,
            'uuid_local' => $offline ? $this->uuid() : null,
            'capturada_en' => $capturada,
            'origen' => $offline ? 'offline' : 'online',
            'sincronizada_en' => $offline ? $capturada->copy()->addMinutes(mt_rand(30, 300)) : null,
            'created_at' => $capturada,
            'updated_at' => $capturada,
        ];
    }

    /**
     * Alumnos de 1° A con historial armado a mano, para enseñar cada regla del
     * semáforo (justificadas no cuentan, 3 retardos = 1 falta, todo el periodo):
     *   3  → falta 2 días completos: 12 de 120 = 10.00 %, justo en el corte → medio
     *   4  → nunca falta pero junta 36 retardos = 12 faltas = 10 % → medio
     *   5  → falta 3 días pero los justifica: 0 % → bajo
     *   6  → estuvo en 1° B las primeras 2 semanas (faltó 4 días allá) y lo
     *        cambiaron a 1° A: el % cuenta todo el periodo, no solo su grupo actual
     *   7  → los últimos 3 días seguidos sin venir (para faltas_consecutivas)
     *   8  → dado de baja a la mitad: sus asistencias llegan hasta la semana 2
     *
     * @return array<int, array<int, array{0:int,1:string,2:int,3:string,4:string}>> alumno_id → filas
     */
    private function casosEspeciales(array $clasesPorGrupo): array
    {
        $a = fn (int $pos) => $this->alumnos['1A'][$pos]['alumno'];
        $g1A = $clasesPorGrupo[$this->grupos['1A']['grupo']->id];
        $g1B = $clasesPorGrupo[$this->grupos['1B']['grupo']->id];
        $fechas = array_keys($g1A);

        // Recorre los días de un grupo y decide el estado de cada clase
        $armar = function (array $dias, callable $estado) {
            $filas = [];
            $n = 0;
            foreach ($dias as $fecha => $clases) {
                foreach ($clases as [$sesionId, $hora, $usuarioId]) {
                    $filas[] = [$sesionId, $hora, $usuarioId, $fecha, $estado($fecha, $n++)];
                }
            }

            return $filas;
        };

        $casos = [
            $a(3)->id => $armar($g1A, fn ($f) => in_array($f, [$fechas[4], $fechas[13]], true) ? 'ausente' : 'presente'),
            $a(4)->id => $armar($g1A, fn ($f, $n) => $n % 10 < 3 ? 'retardo' : 'presente'), // 36 de 120
            $a(5)->id => $armar($g1A, fn ($f) => in_array($f, [$fechas[2], $fechas[9], $fechas[15]], true) ? 'justificado' : 'presente'),
            $a(6)->id => array_merge(
                $armar(array_slice($g1B, 0, 10, true), fn ($f) => in_array($f, array_slice(array_keys($g1B), 3, 4), true) ? 'ausente' : 'presente'),
                $armar(array_slice($g1A, 10, null, true), fn () => 'presente'),
            ),
            $a(7)->id => $armar($g1A, fn ($f) => in_array($f, array_slice($fechas, -3), true) ? 'ausente' : 'presente'),
            $a(8)->id => $armar(array_slice($g1A, 0, 10, true), fn ($f, $n) => $n % 7 === 0 ? 'ausente' : 'presente'),
        ];

        $a(8)->update(['activo' => false]);
        $this->intactos = array_keys($casos);
        foreach ([3, 4, 5, 6, 7, 8] as $pos) {
            $this->alumnos['1A'][$pos]['perfil'] = 'cumplido';
        }

        return $casos;
    }

    /**
     * Justificante para: los 'justificado' armados a mano, y 1 de cada 6 días que
     * faltó un alumno que no va en riesgo (de día completo).
     */
    private function justificaciones(): void
    {
        $orientadorDe = [];
        foreach ($this->grupos as $g) {
            $orientadorDe[$g['grupo']->id] = $this->usuarios["orientador.{$g['turno']}"]->id;
        }
        $enRiesgo = collect($this->alumnos)->flatten(1)->where('perfil', 'riesgo')->map(fn ($x) => $x['alumno']->id)->all();

        // Días completos de falta: (alumno, fecha) donde todas sus clases fueron 'ausente'
        $diasCompletos = DB::table('asistencias as a')
            ->join('sesiones_clase as s', 's.id', '=', 'a.sesion_clase_id')
            ->whereNotIn('a.alumno_id', [...$enRiesgo, ...$this->intactos])
            ->groupBy('a.alumno_id', 's.fecha')
            ->havingRaw("SUM(CASE WHEN a.estado = 'ausente' THEN 0 ELSE 1 END) = 0")
            ->orderBy('a.alumno_id')->orderBy('s.fecha')
            ->get(['a.alumno_id', 's.fecha']);

        foreach ($diasCompletos as $i => $d) {
            if ($i % 6 === 0) {
                DB::table('asistencias')->where('alumno_id', $d->alumno_id)
                    ->whereIn('sesion_clase_id', DB::table('sesiones_clase')->where('fecha', $d->fecha)->select('id'))
                    ->update(['estado' => 'justificado']);
            }
        }

        $filas = DB::table('asistencias as a')
            ->join('sesiones_clase as s', 's.id', '=', 'a.sesion_clase_id')
            ->join('horarios as h', 'h.id', '=', 's.horario_id')
            ->where('a.estado', 'justificado')
            ->orderBy('a.id')
            ->get(['a.id', 'a.alumno_id', 's.fecha', 'h.grupo_id'])
            ->map(fn ($a) => [
                'asistencia_id' => $a->id,
                // mismo motivo para todo el día del mismo alumno
                'motivo' => self::MOTIVOS_JUSTIFICANTE[crc32($a->alumno_id.$a->fecha) % count(self::MOTIVOS_JUSTIFICANTE)],
                'evidencia_url' => null,
                'autorizada_por' => $orientadorDe[$a->grupo_id],
                'fecha_autorizacion' => Carbon::parse($a->fecha)->addDay()->setTime(9, 15),
            ])->all();

        foreach (array_chunk($filas, 1000) as $lote) {
            DB::table('justificaciones')->insert($lote);
        }
    }

    // ------------------------------------------------- calificaciones y demás

    /** Parcial 1: quien falta más, saca menos. */
    private function calificaciones(): void
    {
        $filas = [];
        $ahora = now();
        foreach ($this->alumnos as $clave => $lista) {
            $semestre = $this->grupos[$clave]['semestre'];
            foreach ($lista as ['alumno' => $alumno, 'perfil' => $perfil]) {
                [$min, $max] = ['cumplido' => [75, 100], 'medio' => [65, 90], 'riesgo' => [45, 75]][$perfil];
                foreach ($this->materias[$semestre] as $materia) {
                    $valor = mt_rand($min, $max) / 10;
                    $filas[] = [
                        'alumno_id' => $alumno->id,
                        'materia_id' => $materia->id,
                        'periodo_id' => $this->periodo->id,
                        'parcial' => 1,
                        'calificacion' => $materia->tipo_evaluacion === 'alfanumerica' ? ($valor >= 6 ? 'A' : 'NA') : number_format(max($valor, 5), 1),
                        'tipo' => 'regular',
                        'origen' => 'importado',
                        'editado_por' => null,
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ];
                }
            }
        }
        foreach (array_chunk($filas, 1000) as $lote) {
            DB::table('calificaciones')->insert($lote);
        }
    }

    /** Unas observaciones y avisos sobre alumnos en riesgo, y una importación con errores. */
    private function seguimiento(): void
    {
        $textos = [
            'Se platicó con el alumno sobre sus faltas; refiere que trabaja por las tardes para apoyar en casa.',
            'Se citó a la madre; se compromete a estar al pendiente de la asistencia.',
            'Refiere problemas de transporte desde su comunidad. Se canaliza a trabajo social.',
            'El alumno se compromete a regularizar su asistencia y entregar trabajos pendientes.',
        ];
        $k = 0;
        foreach ($this->alumnos as $clave => $lista) {
            $turno = $this->grupos[$clave]['turno'];
            foreach ($lista as ['alumno' => $alumno, 'perfil' => $perfil]) {
                if ($perfil !== 'riesgo' || $k >= 12) {
                    continue;
                }
                DB::table('observaciones')->insert([
                    'alumno_id' => $alumno->id,
                    'orientador_id' => $this->usuarios["orientador.{$turno}"]->id,
                    'fecha' => Carbon::yesterday()->subDays($k % 5)->toDateString(),
                    'texto' => $textos[$k % 4],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                if ($k < 3) {
                    $tutor = $alumno->padresTutores()->first();
                    DB::table('notificaciones')->insert([
                        'alumno_id' => $alumno->id,
                        'padre_tutor_id' => $tutor->id,
                        'tipo' => 'automatica',
                        'canal' => $tutor->canal_preferido,
                        'estado' => ['enviada', 'pendiente', 'fallida'][$k],
                        'mensaje' => "COBACH 35: le informamos que {$alumno->nombre} acumula inasistencias. Favor de comunicarse con orientación.",
                        'fecha_envio' => $k === 0 ? Carbon::yesterday()->setTime(16, 30) : null,
                        'error_detalle' => $k === 2 ? 'El proveedor rechazó el número: fuera de servicio' : null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                $k++;
            }
        }

        $importacion = Importacion::create([
            'usuario_id' => $this->usuarios['admin']->id,
            'periodo_id' => $this->periodo->id,
            'tipo' => 'alumnos',
            'nombre_archivo' => 'alumnos_2026B.xlsx',
            'filas_procesadas' => 317,
            'filas_error' => 2,
            'estado' => 'con_errores',
        ]);
        $importacion->errores()->createMany([
            ['fila' => 41, 'columna' => 'matricula', 'mensaje' => 'La matrícula 2603500 está incompleta.'],
            ['fila' => 208, 'columna' => 'grupo', 'mensaje' => 'El grupo 3H no existe en el periodo 2026 B.'],
        ]);
    }

    // -------------------------------------------------------------- utilidades

    private function elegir(array $lista): string
    {
        return $lista[mt_rand(0, count($lista) - 1)];
    }

    /** @return array{0: string, 1: string} dos apellidos distintos */
    private function dosApellidos(): array
    {
        $paterno = $this->elegir(self::APELLIDOS);
        do {
            $materno = $this->elegir(self::APELLIDOS);
        } while ($materno === $paterno);

        return [$paterno, $materno];
    }

    /** UUID v4 a partir de mt_rand: determinista con la semilla. */
    private function uuid(): string
    {
        return sprintf('%04x%04x-%04x-4%03x-%04x-%04x%04x%04x', mt_rand(0, 0xFFFF), mt_rand(0, 0xFFFF), mt_rand(0, 0xFFFF), mt_rand(0, 0xFFF), mt_rand(0, 0x3FFF) | 0x8000, mt_rand(0, 0xFFFF), mt_rand(0, 0xFFFF), mt_rand(0, 0xFFFF));
    }
}
