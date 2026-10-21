<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\Grado;
use App\Models\Grupo;
use App\Models\Horario;
use App\Models\Materia;
use App\Models\PeriodoEscolar;
use App\Models\Plantel;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Carga manual: horarios (docente ↔ grupo) y alumnos con su inscripción. Datos del PruebasSeeder. */
class CargaManualTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function como(string $username): static
    {
        return $this->actingAs(Usuario::where('username', $username)->firstOrFail());
    }

    private function grupo(string $grado, string $letra): Grupo
    {
        return Grupo::whereRelation('grado', 'nombre', $grado)->where('letra', $letra)->firstOrFail();
    }

    private function materia(string $clave): int
    {
        return Materia::where('clave', $clave)->value('id');
    }

    /** El caso del docente recién creado: entra, no ve nada, se le pone horario y ya ve su grupo y su lista. */
    public function test_docente_nuevo_recibe_grupo_por_horario_y_ve_su_lista(): void
    {
        $this->como('admin');
        $u = $this->postJson('/api/usuarios', [
            'nombre' => 'Jorge Octavio Guzmán', 'username' => 'jogsd', 'password' => 'secreta123',
            'rol_id' => Rol::where('nombre', 'docente')->value('id'),
        ])->assertCreated()->json();

        $this->como('jogsd')->getJson('/api/mis-horarios')->assertOk()->assertExactJson([]);
        $b1 = $this->grupo('1', 'B');
        $this->getJson("/api/grupos/{$b1->id}/alumnos")->assertForbidden();

        $docentes = $this->como('admin')->getJson('/api/catalogos/docentes')->assertOk()->json();
        $docenteId = collect($docentes)->firstWhere('username', 'jogsd')['id'];
        $this->assertNotContains('admin', array_column($docentes, 'username'));

        $h = $this->postJson('/api/horarios', [
            'grupo_id' => $b1->id, 'materia_id' => $this->materia('A0023'), 'docente_id' => $docenteId,
            'dia_semana' => 5, 'hora_inicio' => '09:00', 'hora_fin' => '09:50',
        ])->assertCreated()->assertJson([
            'dia_semana' => 5, 'hora_inicio' => '09:00', 'hora_fin' => '09:50', 'listas_capturadas' => 0,
            'docente' => ['id' => $docenteId, 'usuario_id' => $u['id'], 'nombre' => 'Jorge Octavio Guzmán'],
            'grupo' => ['id' => $b1->id],
        ])->json();

        $this->getJson("/api/horarios?grupo_id={$b1->id}")->assertOk()->assertJsonFragment(['id' => $h['id']]);
        $this->getJson("/api/horarios?docente_id={$docenteId}")->assertOk()->assertJsonCount(1);
        $this->getJson('/api/horarios')->assertUnprocessable();

        // Alumno nuevo inscrito en 1° B: aparece en la lista del docente
        $alumno = $this->postJson('/api/alumnos', [
            'matricula' => '26035999001', 'nombre' => '  López   Ruiz Ana ', 'grupo_id' => $b1->id,
        ])->assertCreated()->assertJson([
            'nombre' => 'López Ruiz Ana', 'situacion_academica' => 'regular', 'activo' => true, 'grupo' => ['id' => $b1->id],
        ])->json();

        $this->como('jogsd')->getJson('/api/mis-horarios')->assertOk()->assertJsonCount(1)
            ->assertJsonPath('0.grupo.id', $b1->id);
        $this->getJson("/api/grupos/{$b1->id}/alumnos")->assertOk()->assertJsonFragment(['id' => $alumno['id']]);
    }

    public function test_choques_de_grupo_y_de_docente(): void
    {
        $this->como('admin');
        $a1 = $this->grupo('1', 'A');
        $docente1 = Usuario::where('username', 'docente1')->first()->docente->id;
        $docente2 = Usuario::where('username', 'docente2')->first()->docente->id;
        $base = ['materia_id' => $this->materia('A0023'), 'dia_semana' => 1];

        // 1° A ya tiene Matemáticas I el lunes 07:00–07:50
        $this->postJson('/api/horarios', $base + ['grupo_id' => $a1->id, 'docente_id' => $docente2, 'hora_inicio' => '07:30', 'hora_fin' => '08:20'])
            ->assertStatus(409)->assertJson(['message' => 'El grupo ya tiene Matemáticas I (07:00–07:50) el lunes.']);

        // Pegado (empieza cuando termina la otra) no choca
        $this->postJson('/api/horarios', $base + ['grupo_id' => $a1->id, 'docente_id' => $docente2, 'hora_inicio' => '07:50', 'hora_fin' => '08:40'])
            ->assertCreated();

        // Termina justo cuando empieza otra: tampoco choca (1° B tiene 07:50–08:40 el lunes)
        $this->postJson('/api/horarios', $base + ['grupo_id' => $this->grupo('1', 'B')->id, 'docente_id' => $docente2, 'hora_inicio' => '07:00', 'hora_fin' => '07:50'])
            ->assertCreated();

        // docente1 ya está en 1° B el lunes 07:50–08:40
        $this->postJson('/api/horarios', $base + ['grupo_id' => $this->grupo('3', 'A')->id, 'docente_id' => $docente1, 'hora_inicio' => '08:00', 'hora_fin' => '08:30'])
            ->assertStatus(409)->assertJsonPath('message', 'El docente ya da Matemáticas I (07:50–08:40) en 1° B el lunes.');

        $this->postJson('/api/horarios', $base + ['grupo_id' => $a1->id, 'docente_id' => $docente2, 'hora_inicio' => '12:00', 'hora_fin' => '11:00'])
            ->assertUnprocessable()->assertJsonValidationErrors('hora_fin');
        $this->postJson('/api/horarios', ['dia_semana' => 7] + $base + ['grupo_id' => $a1->id, 'docente_id' => $docente2, 'hora_inicio' => '12:00', 'hora_fin' => '13:00'])
            ->assertUnprocessable()->assertJsonValidationErrors('dia_semana');
    }

    public function test_clase_con_listas_solo_cambia_docente_y_horas(): void
    {
        $this->como('admin');
        $conListas = Horario::has('sesiones')->firstOrFail();
        $docente2 = Usuario::where('username', 'docente2')->first()->docente->id;
        $cuerpo = $conListas->only(['grupo_id', 'materia_id', 'dia_semana']) + [
            'docente_id' => $docente2, 'hora_inicio' => '12:00', 'hora_fin' => '12:50',
        ];

        $this->putJson("/api/horarios/{$conListas->id}", ['dia_semana' => 6] + $cuerpo)->assertStatus(409);
        $this->putJson("/api/horarios/{$conListas->id}", $cuerpo)->assertOk()
            ->assertJson(['docente' => ['id' => $docente2], 'hora_inicio' => '12:00']);
        $this->deleteJson("/api/horarios/{$conListas->id}")->assertStatus(409);

        // Sin listas sí se borra; y editarlo a sí mismo no choca consigo
        $nuevo = Horario::create(['grupo_id' => $conListas->grupo_id, 'materia_id' => $conListas->materia_id, 'docente_id' => $docente2, 'dia_semana' => 6, 'hora_inicio' => '08:00:00', 'hora_fin' => '08:50:00']);
        $this->putJson("/api/horarios/{$nuevo->id}", $nuevo->only(['grupo_id', 'materia_id', 'docente_id', 'dia_semana']) + ['hora_inicio' => '08:10', 'hora_fin' => '09:00'])->assertOk();
        $this->deleteJson("/api/horarios/{$nuevo->id}")->assertNoContent();
        $this->assertModelMissing($nuevo);
    }

    public function test_solo_grupos_del_periodo_activo_y_permisos(): void
    {
        $viejo = PeriodoEscolar::create(['nombre' => '2026 A', 'estado' => 'cerrado']);
        $g = Grupo::create(['grado_id' => Grado::first()->id, 'plantel_id' => Plantel::first()->id, 'periodo_id' => $viejo->id, 'letra' => 'Z', 'turno' => 'matutino']);

        $this->como('admin');
        $this->postJson('/api/alumnos', ['matricula' => '1', 'nombre' => 'X', 'grupo_id' => $g->id])
            ->assertUnprocessable()->assertJsonValidationErrors(['grupo_id' => 'El grupo no es del periodo activo.']);
        $this->postJson('/api/horarios', ['grupo_id' => $g->id, 'materia_id' => $this->materia('A0021'), 'docente_id' => Usuario::where('username', 'docente1')->first()->docente->id, 'dia_semana' => 1, 'hora_inicio' => '07:00', 'hora_fin' => '08:00'])
            ->assertUnprocessable()->assertJsonValidationErrors('grupo_id');

        $this->como('orientador1');
        $this->postJson('/api/horarios', [])->assertForbidden();
        $this->postJson('/api/alumnos', [])->assertForbidden();
        $this->getJson('/api/catalogos/docentes')->assertForbidden();
    }

    public function test_editar_alumno_cambio_de_grupo_y_baja(): void
    {
        $this->como('admin');
        $a1 = $this->grupo('1', 'A');
        $b1 = $this->grupo('1', 'B');
        $alumno = Alumno::whereHas('inscripciones', fn ($q) => $q->where('grupo_id', $a1->id))->has('asistencias')->firstOrFail();
        $otro = Alumno::whereKeyNot($alumno->id)->first();
        $riesgoAntes = $this->getJson("/api/alumnos/{$alumno->id}")->json('riesgo');

        $this->putJson("/api/alumnos/{$alumno->id}", ['matricula' => $otro->matricula, 'nombre' => $alumno->nombre])
            ->assertUnprocessable()->assertJsonValidationErrors('matricula');

        $this->putJson("/api/alumnos/{$alumno->id}", [
            'matricula' => $alumno->matricula, 'nombre' => $alumno->nombre, 'grupo_id' => $b1->id, 'situacion_academica' => 'irregular',
        ])->assertOk()->assertJson(['grupo' => ['id' => $b1->id], 'situacion_academica' => 'irregular']);

        // La asistencia de 1° A se conserva y sigue contando
        $this->assertSame($riesgoAntes, $this->getJson("/api/alumnos/{$alumno->id}")->assertJsonPath('grupo.id', $b1->id)->json('riesgo'));
        $this->getJson("/api/alumnos?grupo_id={$a1->id}")->assertJsonMissing(['id' => $alumno->id]);

        // Editar sin grupo_id no toca la inscripción; activo=false es la baja
        $this->putJson("/api/alumnos/{$alumno->id}", ['matricula' => $alumno->matricula, 'nombre' => 'Nuevo Nombre', 'activo' => false])
            ->assertOk()->assertJson(['nombre' => 'Nuevo Nombre', 'activo' => false, 'grupo' => ['id' => $b1->id], 'situacion_academica' => 'irregular']);
    }
}
