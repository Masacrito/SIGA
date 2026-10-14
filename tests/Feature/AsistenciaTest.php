<?php

namespace Tests\Feature;

use App\Models\Asistencia;
use App\Models\Horario;
use App\Models\Inscripcion;
use App\Models\SesionClase;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Bloque 2.5: pasar lista online y sincronizar offline (datos del PruebasSeeder). */
class AsistenciaTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private Horario $horario;

    private string $fecha;

    /** @var int[] */
    private array $alumnos;

    protected function setUp(): void
    {
        parent::setUp();
        $docente1 = Usuario::where('username', 'docente1')->first();
        $this->actingAs($docente1);
        $this->horario = Horario::where('docente_id', $docente1->docente->id)->first();
        // Una fecha futura en el día del horario: sin sesión previa del seeder
        $this->fecha = now()->addWeeks(2)->startOfWeek()->addDays($this->horario->dia_semana - 1)->toDateString();
        $this->alumnos = Inscripcion::where('grupo_id', $this->horario->grupo_id)->orderBy('alumno_id')->pluck('alumno_id')->all();
    }

    private function abrirSesion(): int
    {
        return $this->postJson('/api/sesiones-clase', ['horario_id' => $this->horario->id, 'fecha' => $this->fecha])
            ->assertCreated()->json('id');
    }

    public function test_abrir_sesion_es_idempotente_y_valida_dia_y_dueno(): void
    {
        $id = $this->abrirSesion();
        $this->assertSame($id, $this->abrirSesion());

        $otroDia = now()->addWeeks(2)->startOfWeek()->addDays($this->horario->dia_semana % 7)->toDateString();
        $this->postJson('/api/sesiones-clase', ['horario_id' => $this->horario->id, 'fecha' => $otroDia])
            ->assertUnprocessable()->assertJsonValidationErrors('fecha');

        $ajeno = Horario::where('docente_id', '!=', $this->horario->docente_id)->first();
        $this->postJson('/api/sesiones-clase', ['horario_id' => $ajeno->id, 'fecha' => $this->fecha])->assertForbidden();

        SesionClase::find($id)->update(['cancelada' => true, 'motivo_cancelacion' => 'Junta de academia']);
        $this->postJson('/api/sesiones-clase', ['horario_id' => $this->horario->id, 'fecha' => $this->fecha])
            ->assertStatus(409)->assertJson(['message' => 'La sesión de clase está cancelada: Junta de academia']);
    }

    public function test_pasar_lista_online_uno_y_lote(): void
    {
        $sesion = $this->abrirSesion();
        [$a, $b, $c] = $this->alumnos;

        $this->postJson('/api/asistencias', ['sesion_clase_id' => $sesion, 'alumno_id' => $a, 'estado' => 'presente'])
            ->assertCreated()->assertJson(['origen' => 'online', 'estado' => 'presente']);
        $this->postJson('/api/asistencias', ['sesion_clase_id' => $sesion, 'alumno_id' => $a, 'estado' => 'ausente'])->assertStatus(409);
        $this->postJson('/api/asistencias', ['sesion_clase_id' => $sesion, 'alumno_id' => 999999, 'estado' => 'presente'])
            ->assertUnprocessable()->assertJsonValidationErrors('alumno_id');

        $r = $this->postJson('/api/asistencias/lote', ['sesion_clase_id' => $sesion, 'registros' => [
            ['alumno_id' => $a, 'estado' => 'presente'],
            ['alumno_id' => $b, 'estado' => 'ausente'],
            ['alumno_id' => $c, 'estado' => 'retardo'],
            ['alumno_id' => 999999, 'estado' => 'presente'],
        ]])->assertCreated();
        $this->assertSame(2, $r->json('creadas'));
        $this->assertEqualsCanonicalizing([$a, 999999], array_column($r->json('errores'), 'alumno_id'));
    }

    public function test_sincronizar_offline_creado_duplicado_conflicto_error(): void
    {
        [$a, $b] = $this->alumnos;
        $uuid = (string) Str::uuid();
        $lista = fn (array $registros) => ['listas' => [['horario_id' => $this->horario->id, 'fecha' => $this->fecha, 'registros' => $registros]]];
        $reg = fn ($uuid, $alumno) => ['uuid_local' => $uuid, 'alumno_id' => $alumno, 'estado' => 'presente', 'capturada_en' => $this->fecha.'T07:32:10-06:00'];

        $estados = fn ($r) => array_column($r->json('resultados'), 'estado_sync');

        $r = $this->postJson('/api/asistencias/sincronizar', $lista([$reg($uuid, $a), $reg((string) Str::uuid(), 999999)]))->assertOk();
        $this->assertSame(['creado', 'error'], $estados($r));
        $this->assertSame($this->fecha.' 07:32:10', Asistencia::firstWhere('uuid_local', $uuid)->capturada_en->format('Y-m-d H:i:s'));

        // Reintento del mismo envío + otro uuid para el mismo alumno
        $r = $this->postJson('/api/asistencias/sincronizar', $lista([$reg($uuid, $a), $reg((string) Str::uuid(), $a), $reg((string) Str::uuid(), $b)]))->assertOk();
        $this->assertSame(['duplicado', 'conflicto', 'creado'], $estados($r));
        $this->assertSame(2, Asistencia::whereHas('sesion', fn ($q) => $q->whereDate('fecha', $this->fecha))->count());

        // Sin uuid_local: 422 de validación
        $this->postJson('/api/asistencias/sincronizar', $lista([['alumno_id' => $a, 'estado' => 'presente', 'capturada_en' => now()->toIso8601String()]]))
            ->assertUnprocessable();
    }

    public function test_sincronizar_horario_ajeno_es_error_por_registro(): void
    {
        $ajeno = Horario::where('docente_id', '!=', $this->horario->docente_id)->first();
        $fecha = now()->addWeeks(2)->startOfWeek()->addDays($ajeno->dia_semana - 1)->toDateString();
        $r = $this->postJson('/api/asistencias/sincronizar', ['listas' => [['horario_id' => $ajeno->id, 'fecha' => $fecha, 'registros' => [
            ['uuid_local' => (string) Str::uuid(), 'alumno_id' => 1, 'estado' => 'presente', 'capturada_en' => now()->toIso8601String()],
        ]]]])->assertOk();
        $this->assertSame('error', $r->json('resultados.0.estado_sync'));
        $this->assertSame(0, SesionClase::where('horario_id', $ajeno->id)->where('fecha', $fecha)->count());
    }

    public function test_roles_y_permisos(): void
    {
        $this->getJson('/api/roles')->assertForbidden();

        $this->actingAs(Usuario::where('username', 'admin')->first());
        $roles = $this->getJson('/api/roles')->assertOk()->json();
        $docente = collect($roles)->firstWhere('nombre', 'docente');
        $this->assertArrayNotHasKey('pivot', $docente['permisos'][0]);
        $ids = array_column($this->getJson('/api/permisos')->assertOk()->json(), 'id');

        $this->putJson("/api/roles/{$docente['id']}/permisos", ['permiso_ids' => array_slice($ids, 0, 2)])
            ->assertOk()->assertJsonCount(2, 'permisos');
        $this->putJson("/api/roles/{$docente['id']}/permisos", ['permiso_ids' => [999999]])->assertUnprocessable();
    }
}
