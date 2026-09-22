<?php

namespace Database\Seeders;

use App\Models\Alumno;
use App\Models\Asistencia;
use App\Models\Calificacion;
use App\Models\Docente;
use App\Models\Grado;
use App\Models\Grupo;
use App\Models\Horario;
use App\Models\Importacion;
use App\Models\Inscripcion;
use App\Models\Justificacion;
use App\Models\Materia;
use App\Models\Notificacion;
use App\Models\Observacion;
use App\Models\PadreTutor;
use App\Models\PeriodoEscolar;
use App\Models\Plantel;
use App\Models\SesionClase;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**

 
class DemoSeeder extends Seeder
{
    /** UUID ya sincronizado: reenviarlo debe responder "duplicado". */
    public const UUID_DUPLICADO = '00000000-0000-4000-8000-000000000001';

    /** Ya hay registro de ese alumno en esa sesión: un uuid nuevo debe dar "conflicto". */
    public const UUID_CONFLICTO_EXISTENTE = '00000000-0000-4000-8000-000000000002';

    private const ALUMNOS_POR_GRUPO = 25;

    private PeriodoEscolar $periodo;

    /** @var array<string, Usuario> */
    private array $usuarios = [];

    /** @var array<string, Grupo> */
    private array $grupos = [];

    /** @var array<string, Materia> */
    private array $materias = [];

    /** @var array<string, Collection<int, Alumno>> alumnos por grupo, en orden de lista (posición 1..25) */
    private array $alumnos = [];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->error('DemoSeeder solo corre en local o testing.');

            return;
        }

        if (Usuario::where('username', 'docente1')->exists()) {
            $this->command?->warn('Los datos demo ya existen; usa migrate:fresh --seed para regenerarlos.');

            return;
        }

        fake()->seed(35);
        mt_srand(35);

        $this->periodo = PeriodoEscolar::activo();

        DB::transaction(function () {
            $this->usuarios();
            $this->grupos();
            $this->materias();
            $this->alumnosYTutores();
            $horarios = $this->horarios();
            $this->sesionesYAsistencias($horarios);
            $this->justificaciones();
            $this->calificaciones($horarios);
            $this->observacionesYNotificaciones();
            $this->importacion();
        });
    }

    private function usuarios(): void
    {
        $datos = [
            'admin' => ['administrador', 'Administrador del sistema'],
            'orientador1' => ['orientador', 'María Elena Cruz Gómez'],
            'orientador2' => ['orientador', 'Jorge Alberto Nájera Solís'],
            'docente1' => ['docente', 'Juan Carlos Méndez Ruiz'],
            'docente2' => ['docente', 'Patricia Hernández Velasco'],
        ];

        foreach ($datos as $username => [$rol, $nombre]) {
            $this->usuarios[$username] = Usuario::factory()->conRol($rol)->create([
                'username' => $username,
                'nombre' => $nombre,
                'correo' => "{$username}@cobach35.test",
            ]);
        }

        Docente::create(['usuario_id' => $this->usuarios['docente1']->id, 'numero_empleado' => 'D-0001']);
        Docente::create(['usuario_id' => $this->usuarios['docente2']->id, 'numero_empleado' => 'D-0002']);
    }

    private function grupos(): void
    {
        $plantel = Plantel::first();

        foreach ([['1', 'A', 'matutino'], ['1', 'B', 'matutino'], ['3', 'A', 'vespertino']] as [$grado, $letra, $turno]) {
            $this->grupos[$grado.$letra] = Grupo::create([
                'grado_id' => Grado::where('nombre', $grado)->value('id'),
                'plantel_id' => $plantel->id,
                'periodo_id' => $this->periodo->id,
                'letra' => $letra,
                'turno' => $turno,
            ]);
        }

        // orientador1 → 1A y 1B; orientador2 → 3A (probar que uno no ve los grupos del otro)
        $this->usuarios['orientador1']->gruposOrientados()->attach([$this->grupos['1A']->id, $this->grupos['1B']->id]);
        $this->usuarios['orientador2']->gruposOrientados()->attach([$this->grupos['3A']->id]);
    }

    private function materias(): void
    {
        $lista = [
            ['A0021', 'Matemáticas I', 'numerica'],
            ['A0022', 'Química I', 'numerica'],
            ['A0023', 'Taller de Lectura y Redacción I', 'numerica'],
            ['A0024', 'Inglés I', 'numerica'],
            ['OE', 'Orientación Educativa', 'alfanumerica'],
        ];

        foreach ($lista as [$clave, $nombre, $tipo]) {
            $this->materias[$clave] = Materia::create(['clave' => $clave, 'nombre' => $nombre, 'tipo_evaluacion' => $tipo]);
        }
    }

    private function alumnosYTutores(): void
    {
        $situaciones = [5 => 'irregular', 12 => 'irregular', 8 => 'recursador', 20 => 'extemporaneo'];
        $consecutivo = 0;

        foreach ($this->grupos as $clave => $grupo) {
            $lista = collect();

            for ($pos = 1; $pos <= self::ALUMNOS_POR_GRUPO; $pos++) {
                $consecutivo++;
                $alumno = Alumno::factory()->create([
                    'matricula' => sprintf('26071035%06d', $consecutivo),
                ]);

                Inscripcion::create([
                    'alumno_id' => $alumno->id,
                    'grupo_id' => $grupo->id,
                    'periodo_id' => $this->periodo->id,
                    'situacion_academica' => $situaciones[$pos] ?? 'regular',
                    'fecha_inscripcion' => $this->periodo->fecha_inicio,
                ]);

                // Tutor principal; uno de cada 4 prefiere correo
                $tutor = ($pos % 4 === 0 ? PadreTutor::factory()->porCorreo() : PadreTutor::factory())->create();
                $alumno->padresTutores()->attach($tutor->id, [
                    'parentesco' => $pos % 2 ? 'madre' : 'padre',
                    'es_principal' => true,
                ]);

                // Uno de cada 6 tiene un segundo tutor (no principal)
                if ($pos % 6 === 0) {
                    $alumno->padresTutores()->attach(PadreTutor::factory()->create()->id, [
                        'parentesco' => 'abuela',
                        'es_principal' => false,
                    ]);
                }

                $lista->push($alumno);
            }

            $this->alumnos[$clave] = $lista;
        }

        // Dos bajas
        $this->alumnos['1B'][9]->update(['activo' => false]);
        $this->alumnos['3A'][6]->update(['activo' => false]);

        // Hermanos: el 4 de 1A comparte al tutor principal del 3 de 1B (relación N:M)
        $tutorHermano = $this->alumnos['1B'][2]->padresTutores()->wherePivot('es_principal', true)->first();
        $this->alumnos['1A'][3]->padresTutores()->attach($tutorHermano->id, [
            'parentesco' => 'madre',
            'es_principal' => false,
        ]);
    }

    /** @return Collection<int, Horario> */
    private function horarios(): Collection
    {
        $d1 = $this->usuarios['docente1']->docente->id;
        $d2 = $this->usuarios['docente2']->docente->id;

        // [grupo, materia, docente, día (1=lunes), inicio, fin]
        $tabla = [
            ['1A', 'A0021', $d1, 1, '07:00', '07:50'],
            ['1A', 'A0021', $d1, 3, '07:00', '07:50'],
            ['1A', 'A0022', $d1, 2, '08:00', '08:50'],
            ['1A', 'A0024', $d2, 4, '10:00', '10:50'],
            ['1B', 'A0021', $d1, 1, '07:50', '08:40'],
            ['1B', 'A0021', $d1, 3, '07:50', '08:40'],
            ['1B', 'A0022', $d1, 2, '08:50', '09:40'],
            ['1B', 'A0024', $d2, 4, '10:50', '11:40'],
            ['3A', 'A0023', $d2, 1, '14:00', '14:50'],
            ['3A', 'A0024', $d2, 2, '14:00', '14:50'],
            ['3A', 'A0023', $d2, 4, '14:00', '14:50'],
            ['3A', 'OE', $d2, 5, '15:00', '15:50'],
        ];

        return collect($tabla)->map(fn ($h) => Horario::create([
            'grupo_id' => $this->grupos[$h[0]]->id,
            'materia_id' => $this->materias[$h[1]]->id,
            'docente_id' => $h[2],
            'dia_semana' => $h[3],
            'hora_inicio' => $h[4],
            'hora_fin' => $h[5],
        ]));
    }

    /**
     * Perfiles fijos por posición en la lista para que el semáforo muestre
     * los 3 colores (el % sale sobre ~15-16 sesiones por alumno):
     *   1-18 → bajo  (0 o 1 falta, ~6%)
     *   19-22 → medio (~13%)
     *   23-24 → alto  (~25%)
     *   25    → alto  (~31%); en 1A sus últimas 3 sesiones son faltas seguidas
     */
    private function faltasObjetivo(int $pos, int $sesiones): int
    {
        return match (true) {
            $pos <= 18 => $pos % 3 === 0 ? (int) floor($sesiones * 0.07) : 0,
            $pos <= 22 => (int) floor($sesiones * 0.15),
            $pos <= 24 => (int) floor($sesiones * 0.30),
            default => (int) floor($sesiones * 0.35),
        };
    }

    /** @param Collection<int, Horario> $horarios */
    private function sesionesYAsistencias(Collection $horarios): void
    {
        $hoy = Carbon::today();
        $desde = $hoy->copy()->subDays(28); // 28 días = exactamente 4 de cada día de la semana
        $docentePorId = Docente::with('usuario')->get()->keyBy('id');

        // 1) Sesiones de cada horario, solo en su día de la semana, hasta ayer
        $sesionesPorGrupo = [];
        foreach ($horarios as $h) {
            for ($f = $desde->copy(); $f->lt($hoy); $f->addDay()) {
                if ($f->dayOfWeekIso !== $h->dia_semana) {
                    continue;
                }
                $sesion = SesionClase::create(['horario_id' => $h->id, 'fecha' => $f->toDateString()]);
                $claveGrupo = array_search($h->grupo_id, array_map(fn ($g) => $g->id, $this->grupos), true);
                $sesionesPorGrupo[$claveGrupo][] = ['sesion' => $sesion, 'horario' => $h];
            }
        }

        // Una sesión cancelada (la primera de 1A): no lleva asistencias
        $cancelada = $sesionesPorGrupo['1A'][0]['sesion'];
        $cancelada->update(['cancelada' => true, 'motivo_cancelacion' => 'Suspensión por junta de academia']);

        $filas = [];
        $ahora = now();

        foreach ($sesionesPorGrupo as $claveGrupo => $sesiones) {
            // Orden cronológico y sin la cancelada
            $validas = collect($sesiones)
                ->reject(fn ($s) => $s['sesion']->cancelada)
                ->sortBy(fn ($s) => $s['sesion']->fecha->format('Y-m-d').' '.$s['horario']->hora_inicio)
                ->values();
            $total = $validas->count();

            foreach ($this->alumnos[$claveGrupo] as $i => $alumno) {
                $pos = $i + 1;
                $nFaltas = $this->faltasObjetivo($pos, $total);

                if ($claveGrupo === '1A' && $pos === 25) {
                    // Las últimas 3 seguidas (para probar faltas_consecutivas) + las demás al azar
                    $faltas = range($total - 3, $total - 1);
                    $resto = range(0, $total - 4);
                    shuffle($resto);
                    $faltas = array_merge($faltas, array_slice($resto, 0, $nFaltas - 3));
                } else {
                    $indices = range(0, $total - 1);
                    shuffle($indices);
                    $faltas = array_slice($indices, 0, $nFaltas);
                }

                // ~5%: un retardo para los de posición múltiplo de 5 (no cuenta como falta)
                $retardo = $pos % 5 === 0 ? (($pos * 7) % $total) : null;
                if ($retardo !== null && in_array($retardo, $faltas, true)) {
                    $retardo = null;
                }

                foreach ($validas as $k => $s) {
                    /** @var SesionClase $sesion */
                    $sesion = $s['sesion'];
                    $capturada = Carbon::parse($sesion->fecha->format('Y-m-d').' '.$s['horario']->hora_inicio)->addMinutes(5);
                    $offline = $k % 4 === 1; // una de cada 4 sesiones se capturó sin señal

                    $filas[] = [
                        'sesion_clase_id' => $sesion->id,
                        'alumno_id' => $alumno->id,
                        'registrado_por' => $docentePorId[$s['horario']->docente_id]->usuario->id,
                        'estado' => in_array($k, $faltas, true) ? 'ausente' : ($k === $retardo ? 'retardo' : 'presente'),
                        'uuid_local' => $offline ? fake()->uuid() : null,
                        'capturada_en' => $capturada,
                        'origen' => $offline ? 'offline' : 'online',
                        'sincronizada_en' => $offline ? $capturada->copy()->addHours(3) : null,
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ];
                }
            }
        }

        foreach (array_chunk($filas, 500) as $lote) {
            Asistencia::insert($lote);
        }

        // Casos fijos para probar la sincronización offline desde la app:
        // en la sesión más reciente de 1A, el alumno 1 ya tiene UUID_DUPLICADO
        // y el alumno 2 ya tiene UUID_CONFLICTO_EXISTENTE.
        $ultima = collect($sesionesPorGrupo['1A'])->map(fn ($s) => $s['sesion'])
            ->sortBy(fn ($s) => $s->fecha->format('Y-m-d').' '.$s->horario->hora_inicio)->last();

        foreach ([0 => self::UUID_DUPLICADO, 1 => self::UUID_CONFLICTO_EXISTENTE] as $idx => $uuid) {
            Asistencia::where('sesion_clase_id', $ultima->id)
                ->where('alumno_id', $this->alumnos['1A'][$idx]->id)
                ->update(['uuid_local' => $uuid, 'origen' => 'offline', 'sincronizada_en' => $ahora]);
        }
    }

    /** 5 justificantes sobre faltas de alumnos de riesgo bajo (siguen en verde). */
    private function justificaciones(): void
    {
        $idsBajo = collect(['1A', '1B'])
            ->flatMap(fn ($g) => $this->alumnos[$g]->slice(0, 18))
            ->pluck('id');

        Asistencia::whereIn('alumno_id', $idsBajo)
            ->where('estado', 'ausente')
            ->orderBy('id')
            ->take(5)
            ->get()
            ->each(function (Asistencia $a) {
                Justificacion::create([
                    'asistencia_id' => $a->id,
                    'motivo' => 'Consulta médica (constancia entregada)',
                    'evidencia_url' => null,
                    'autorizada_por' => $this->usuarios['orientador1']->id,
                    'fecha_autorizacion' => $a->capturada_en->copy()->addDay(),
                ]);
                $a->update(['estado' => 'justificado']);
            });
    }

    /** @param Collection<int, Horario> $horarios */
    private function calificaciones(Collection $horarios): void
    {
        $manuales = 0;

        foreach ($this->grupos as $clave => $grupo) {
            $materiaIds = $horarios->where('grupo_id', $grupo->id)->pluck('materia_id')->unique();

            foreach ($this->alumnos[$clave] as $i => $alumno) {
                $pos = $i + 1;
                foreach ($materiaIds as $materiaId) {
                    $materia = Materia::find($materiaId);
                    $valor = $materia->tipo_evaluacion === 'alfanumerica'
                        ? ($pos >= 23 ? 'NA' : 'A')
                        : number_format($pos >= 23 ? fake()->randomFloat(1, 5, 7) : fake()->randomFloat(1, 7, 10), 1);

                    $manual = $manuales < 3 && $pos === 10;
                    Calificacion::create([
                        'alumno_id' => $alumno->id,
                        'materia_id' => $materiaId,
                        'periodo_id' => $this->periodo->id,
                        'parcial' => 1,
                        'calificacion' => $valor,
                        'tipo' => 'regular',
                        'origen' => $manual ? 'manual' : 'importado',
                        'editado_por' => $manual ? $this->usuarios['admin']->id : null,
                    ]);
                    $manuales += $manual ? 1 : 0;
                }
            }
        }
    }

    private function observacionesYNotificaciones(): void
    {
        $textos = [
            'Se platicó con el alumno sobre sus inasistencias; refiere problemas de transporte.',
            'Se acordó cita con la madre para la próxima semana.',
            'El alumno se compromete a regularizar su asistencia.',
        ];

        foreach (['1A' => 'orientador1', '1B' => 'orientador1', '3A' => 'orientador2'] as $grupo => $orientador) {
            foreach ([23, 25] as $k => $pos) {
                Observacion::create([
                    'alumno_id' => $this->alumnos[$grupo][$pos - 1]->id,
                    'orientador_id' => $this->usuarios[$orientador]->id,
                    'fecha' => Carbon::yesterday()->subDays($k * 3),
                    'texto' => $textos[($k + strlen($grupo)) % 3],
                ]);
            }
        }

        $casos = [
            ['1A', 25, 'pendiente', null, null],
            ['1B', 25, 'enviada', Carbon::yesterday()->setTime(16, 30), null],
            ['3A', 24, 'fallida', null, 'El proveedor rechazó el número: formato inválido'],
        ];

        foreach ($casos as [$grupo, $pos, $estado, $envio, $error]) {
            $alumno = $this->alumnos[$grupo][$pos - 1];
            $tutor = $alumno->padresTutores()->wherePivot('es_principal', true)->first();

            Notificacion::create([
                'alumno_id' => $alumno->id,
                'padre_tutor_id' => $tutor->id,
                'tipo' => 'automatica',
                'canal' => $tutor->canal_preferido,
                'estado' => $estado,
                'mensaje' => "Le informamos que {$alumno->nombre} acumula inasistencias en riesgo alto. Favor de comunicarse con orientación.",
                'fecha_envio' => $envio,
                'error_detalle' => $error,
            ]);
        }
    }

    private function importacion(): void
    {
        $importacion = Importacion::create([
            'usuario_id' => $this->usuarios['admin']->id,
            'periodo_id' => $this->periodo->id,
            'tipo' => 'alumnos',
            'nombre_archivo' => 'alumnos_2026B.xlsx',
            'filas_procesadas' => 75,
            'filas_error' => 2,
            'estado' => 'con_errores',
        ]);

        $importacion->errores()->createMany([
            ['fila' => 17, 'columna' => 'matricula', 'mensaje' => 'La matrícula 2607103500001 no tiene 14 dígitos.'],
            ['fila' => 63, 'columna' => 'grupo', 'mensaje' => 'El grupo 4Z no existe en el periodo 2026 B.'],
        ]);
    }
}
