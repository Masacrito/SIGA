<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\Asistencia;
use App\Models\Configuracion;
use App\Models\Grupo;
use App\Models\Inscripcion;
use App\Models\PeriodoEscolar;
use App\Models\SesionClase;
use App\Models\UmbralRiesgo;
use App\Models\Usuario;
use App\Support\Semaforo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Paso 1: reglas del semáforo (2026-10-14), semáforo, historial y expediente.
 * Cada regla se prueba con un alumno nuevo de historial armado a mano, para
 * que el % se pueda revisar con lápiz. Base: PruebasSeeder.
 */
class SemaforoTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private int $periodo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->periodo = PeriodoEscolar::activo()->id;
    }

    private function grupo(string $grado, string $letra): Grupo
    {
        return Grupo::whereRelation('grado', 'nombre', $grado)->where('letra', $letra)->firstOrFail();
    }

    /**
     * Alumno nuevo inscrito en $grupo (1A por omisión). $dias: una lista de
     * estados por día, en orden cronológico; cada estado es una clase.
     * $otroGrupo: los primeros días los pasa en ese grupo (cambio de grupo).
     */
    private function alumnoCon(array $dias, ?Grupo $grupo = null, ?Grupo $otroGrupo = null, int $diasEnOtro = 0): Alumno
    {
        $grupo ??= $this->grupo('1', 'A');
        $alumno = Alumno::create(['matricula' => uniqid(), 'nombre' => 'Prueba Prueba Alumno', 'activo' => true]);
        Inscripcion::create(['alumno_id' => $alumno->id, 'grupo_id' => $grupo->id, 'periodo_id' => $this->periodo]);

        $fecha = Carbon::parse('2026-12-01');
        foreach ($dias as $i => $clases) {
            $horarios = ($i < $diasEnOtro ? $otroGrupo : $grupo)->horarios()->orderBy('id')->get();
            foreach ($clases as $k => $estado) {
                $sesion = SesionClase::abrir($horarios[$k]->id, $fecha->toDateString());
                Asistencia::create(['sesion_clase_id' => $sesion->id, 'alumno_id' => $alumno->id, 'registrado_por' => 1, 'estado' => $estado, 'capturada_en' => $fecha]);
            }
            $fecha->addDay();
        }

        return $alumno;
    }

    private function riesgo(Alumno $a): array
    {
        return Semaforo::calcular([$a->id], $this->periodo)[$a->id];
    }

    public function test_justificadas_no_cuentan_y_tres_retardos_son_una_falta(): void
    {
        // 10 clases: 1 ausente, 3 retardos (= 1 falta), 2 justificadas, 4 presentes → 2 faltas = 20 % → alto
        $a = $this->alumnoCon([['ausente', 'retardo'], ['retardo', 'retardo'], ['justificado', 'justificado'], ['presente', 'presente'], ['presente', 'presente']]);
        $r = $this->riesgo($a);
        $this->assertSame([10, 1, 3, 2, 2], [$r['clases'], $r['ausencias'], $r['retardos'], $r['justificadas'], $r['faltas']]);
        $this->assertSame(20.0, (float) $r['porcentaje_inasistencia']);
        $this->assertSame('alto', $r['nivel_riesgo']);

        // Si la institución dice que los retardos no cuentan: 1 falta = 10 % → medio
        Configuracion::where('clave', 'retardos_equivalen_falta')->update(['valor' => '0']);
        $this->assertSame('medio', $this->riesgo($a)['nivel_riesgo']);
    }

    public function test_el_corte_exacto_cae_en_el_nivel_de_arriba(): void
    {
        // 3 de 30 = 10.00 % exacto → medio (bajo es 0 ≤ % < 10)
        $dias = array_fill(0, 10, ['presente', 'presente', 'presente']);
        $dias[4] = ['ausente', 'ausente', 'ausente'];
        $r = $this->riesgo($this->alumnoCon($dias));
        $this->assertSame(10.0, (float) $r['porcentaje_inasistencia']);
        $this->assertSame('medio', $r['nivel_riesgo']);

        $sinClases = $this->riesgo($this->alumnoCon([]));
        $this->assertSame([0, 0.0, 'bajo'], [$sinClases['clases'], (float) $sinClases['porcentaje_inasistencia'], $sinClases['nivel_riesgo']]);
    }

    public function test_cuenta_todo_el_periodo_aunque_cambie_de_grupo(): void
    {
        // 2 días en 1B faltando, luego 2 días en 1A sin faltar: 4 de 8 = 50 %
        $a = $this->alumnoCon([['ausente', 'ausente'], ['ausente', 'ausente'], ['presente', 'presente'], ['presente', 'presente']], otroGrupo: $this->grupo('1', 'B'), diasEnOtro: 2);
        $this->assertSame(50.0, (float) $this->riesgo($a)['porcentaje_inasistencia']);
    }

    public function test_dias_seguidos_sin_venir_suben_de_nivel_si_esta_configurado(): void
    {
        // Falta los últimos 3 días completos; antes, un día faltó solo a una clase (no rompe nada)
        $dias = array_fill(0, 17, ['presente', 'presente']);
        $dias[10] = ['ausente', 'presente'];
        $dias = [...$dias, ['ausente', 'ausente'], ['ausente', 'ausente'], ['ausente', 'ausente']];
        $a = $this->alumnoCon($dias);

        $r = $this->riesgo($a); // 7 de 40 = 17.5 %
        $this->assertSame([3, 'medio'], [$r['racha_dias'], $r['nivel_riesgo']]);

        UmbralRiesgo::whereNull('periodo_id')->where('nivel', 'alto')->update(['faltas_consecutivas' => 3]);
        $this->assertSame('alto', $this->riesgo($a)['nivel_riesgo']);

        // Un día con al menos una clase cortaría la racha
        UmbralRiesgo::whereNull('periodo_id')->where('nivel', 'alto')->update(['faltas_consecutivas' => 4]);
        $this->assertSame('medio', $this->riesgo($a)['nivel_riesgo']);
    }

    public function test_semaforo_por_pertenencia(): void
    {
        $o1 = Usuario::where('username', 'orientador1')->first();
        $filas = $this->actingAs($o1)->getJson('/api/semaforo-riesgo')->assertOk()->json();

        // Solo 1A y 1B (25 + 25), sin la baja de 1B; ordenado del más al menos faltista
        $this->assertCount(49, $filas);
        $this->assertEqualsCanonicalizing(['A', 'B'], array_values(array_unique(array_column(array_column($filas, 'grupo'), 'letra'))));
        $porcentajes = array_column($filas, 'porcentaje_inasistencia');
        $this->assertSame($porcentajes, collect($porcentajes)->sortDesc()->values()->all());
        $this->assertArrayHasKey('racha_dias', $filas[0]);

        $altos = $this->getJson('/api/semaforo-riesgo?nivel=alto')->assertOk()->json();
        $this->assertNotEmpty($altos);
        $this->assertSame(['alto'], array_values(array_unique(array_column($altos, 'nivel_riesgo'))));

        $this->getJson('/api/semaforo-riesgo?grupo_id='.$this->grupo('3', 'A')->id)->assertForbidden();
        $this->actingAs(Usuario::where('username', 'docente1')->first())->getJson('/api/semaforo-riesgo')->assertForbidden();
        $this->actingAs(Usuario::where('username', 'admin')->first())->getJson('/api/semaforo-riesgo')->assertOk()->assertJsonCount(73);
    }

    public function test_historial_y_expediente(): void
    {
        $a = $this->alumnoCon([['ausente', 'ausente'], ['presente', 'justificado']], otroGrupo: $this->grupo('1', 'B'), diasEnOtro: 1);
        Asistencia::where('alumno_id', $a->id)->where('estado', 'justificado')->first()
            ->justificacion()->create(['motivo' => 'Consulta médica', 'autorizada_por' => 1, 'fecha_autorizacion' => now()]);

        $o1 = Usuario::where('username', 'orientador1')->first();
        $this->actingAs($o1)->getJson('/api/asistencias/historial')->assertUnprocessable();

        $h = $this->getJson("/api/asistencias/historial?alumno_id={$a->id}")->assertOk()->json();
        $this->assertCount(4, $h);
        $this->assertSame('2026-12-02', $h[0]['fecha']); // lo más reciente primero
        $this->assertSame(['A', 'B'], [$h[0]['grupo']['letra'], $h[3]['grupo']['letra']]); // incluye su grupo anterior
        $this->assertSame('Consulta médica', collect($h)->firstWhere('estado', 'justificado')['motivo_justificacion']);
        $this->assertArrayHasKey('nombre', $h[0]['materia']);

        $grupo = $this->getJson('/api/asistencias/historial?grupo_id='.$this->grupo('1', 'A')->id.'&fecha_inicio=2026-12-02&fecha_fin=2026-12-02')->assertOk()->json();
        $this->assertSame([$a->id], array_values(array_unique(array_column(array_column($grupo, 'alumno'), 'id'))));

        $e = $this->getJson("/api/alumnos/{$a->id}")->assertOk()->json();
        $this->assertSame('A', $e['grupo']['letra']);
        $this->assertSame([4, 2, 50.0], [$e['riesgo']['clases'], $e['riesgo']['faltas'], (float) $e['riesgo']['porcentaje_inasistencia']]);

        // orientador2 solo ve 3A
        $o2 = Usuario::where('username', 'orientador2')->first();
        $this->actingAs($o2)->getJson("/api/alumnos/{$a->id}")->assertForbidden();
        $this->getJson("/api/asistencias/historial?alumno_id={$a->id}")->assertForbidden();
        $this->assertSame([], $this->getJson('/api/alumnos?nombre=Prueba')->assertOk()->json());
    }
}
