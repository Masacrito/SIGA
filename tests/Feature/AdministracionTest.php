<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\Grupo;
use App\Models\PadreTutor;
use App\Models\PeriodoEscolar;
use App\Models\Rol;
use App\Models\UmbralRiesgo;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Paso 2: administración (usuarios, catálogos, tutores, configuración). Datos del PruebasSeeder. */
class AdministracionTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function como(string $username): static
    {
        return $this->actingAs(Usuario::where('username', $username)->firstOrFail());
    }

    private function rol(string $nombre): int
    {
        return Rol::where('nombre', $nombre)->value('id');
    }

    public function test_crear_editar_y_desactivar_usuario(): void
    {
        $this->como('docente1')->getJson('/api/usuarios')->assertForbidden();

        $this->como('admin');
        $u = $this->postJson('/api/usuarios', [
            'nombre' => 'Ana Ruiz', 'username' => 'aruiz', 'password' => 'secreta123',
            'rol_id' => $this->rol('docente'), 'numero_empleado' => 'E-77',
        ])->assertCreated()->assertJson(['rol' => 'docente', 'numero_empleado' => 'E-77', 'activo' => true])->json();
        $this->assertArrayNotHasKey('password', $u);

        $this->postJson('/api/usuarios', ['nombre' => 'X', 'username' => 'aruiz', 'password' => 'secreta123', 'rol_id' => $this->rol('docente')])
            ->assertUnprocessable()->assertJsonValidationErrors('username');

        // Editar sin password conserva la anterior
        $this->putJson("/api/usuarios/{$u['id']}", ['nombre' => 'Ana Ruiz P.', 'username' => 'aruiz', 'rol_id' => $this->rol('docente'), 'numero_empleado' => 'E-78'])
            ->assertOk()->assertJson(['nombre' => 'Ana Ruiz P.', 'numero_empleado' => 'E-78']);
        $this->postJson('/api/login', ['username' => 'aruiz', 'password' => 'secreta123'])->assertOk();

        // Reactivar/editar sin numero_empleado no lo borra; mandarlo vacío sí
        $this->putJson("/api/usuarios/{$u['id']}", ['nombre' => 'Ana Ruiz P.', 'username' => 'aruiz', 'rol_id' => $this->rol('docente')])
            ->assertOk()->assertJson(['numero_empleado' => 'E-78']);
        $this->putJson("/api/usuarios/{$u['id']}", ['nombre' => 'Ana Ruiz P.', 'username' => 'aruiz', 'rol_id' => $this->rol('docente'), 'numero_empleado' => ''])
            ->assertOk()->assertJson(['numero_empleado' => null]);

        // Desactivar revoca sus tokens y ya no entra
        $usuario = Usuario::find($u['id']);
        $usuario->createToken('movil');
        $this->como('admin')->deleteJson("/api/usuarios/{$u['id']}")->assertNoContent();
        $this->assertFalse($usuario->fresh()->activo);
        $this->assertSame(0, $usuario->tokens()->count());
        $this->postJson('/api/login', ['username' => 'aruiz', 'password' => 'secreta123'])->assertUnauthorized();
    }

    public function test_protecciones_de_cuentas(): void
    {
        $admin = Usuario::where('username', 'admin')->first();
        $this->actingAs($admin)->deleteJson("/api/usuarios/{$admin->id}")->assertUnprocessable();
        $this->putJson("/api/usuarios/{$admin->id}", ['nombre' => 'A', 'username' => 'admin', 'rol_id' => $this->rol('administrador'), 'activo' => false])
            ->assertUnprocessable();

        // Un administrador que no es súper admin no puede tocar al súper admin
        $super = Usuario::where('es_super_admin', true)->firstOrFail();
        $this->assertFalse($admin->es_super_admin);
        $this->putJson("/api/usuarios/{$super->id}", ['nombre' => 'X', 'username' => $super->username, 'password' => 'hackeada1', 'rol_id' => $this->rol('administrador')])
            ->assertForbidden();
        $this->deleteJson("/api/usuarios/{$super->id}")->assertForbidden();
        $this->actingAs($super)->putJson("/api/usuarios/{$super->id}", ['nombre' => 'Súper', 'username' => $super->username, 'rol_id' => $this->rol('administrador')])
            ->assertOk();

        // es_super_admin no se puede escalar por la API
        $this->actingAs($admin)->putJson("/api/usuarios/{$admin->id}", ['nombre' => 'A', 'username' => 'admin', 'rol_id' => $this->rol('administrador'), 'es_super_admin' => true])
            ->assertOk()->assertJson(['es_super_admin' => false]);
        $this->assertFalse($admin->fresh()->es_super_admin);
    }

    public function test_grupos_del_orientador(): void
    {
        $o2 = Usuario::where('username', 'orientador2')->first();
        $g1a = Grupo::whereRelation('grado', 'nombre', '1')->where('letra', 'A')->first();

        $this->como('admin')->getJson("/api/orientadores/{$o2->id}/grupos")->assertOk()->assertJsonCount(1);
        $this->postJson("/api/orientadores/{$o2->id}/grupos", ['grupo_id' => $g1a->id])->assertCreated()->assertJson(['grado' => '1', 'letra' => 'A']);
        $this->postJson("/api/orientadores/{$o2->id}/grupos", ['grupo_id' => $g1a->id])->assertStatus(409);
        $this->assertTrue($o2->puedeVerGrupo($g1a));

        $this->deleteJson("/api/orientadores/{$o2->id}/grupos/{$g1a->id}")->assertNoContent();
        $this->deleteJson("/api/orientadores/{$o2->id}/grupos/{$g1a->id}")->assertNotFound();

        $docente = Usuario::where('username', 'docente1')->first();
        $this->postJson("/api/orientadores/{$docente->id}/grupos", ['grupo_id' => $g1a->id])->assertUnprocessable();

        // Si deja de ser orientador, pierde sus grupos (y con ellos la vista de sus alumnos)
        $this->putJson("/api/usuarios/{$o2->id}", ['nombre' => $o2->nombre, 'username' => $o2->username, 'rol_id' => $this->rol('docente')])->assertOk();
        $this->assertSame(0, $o2->gruposOrientados()->count());
    }

    public function test_catalogos(): void
    {
        $this->como('admin');
        $this->postJson('/api/catalogos/materias', ['clave' => 'B0101', 'nombre' => 'Física I'])->assertCreated()->assertJson(['tipo_evaluacion' => 'numerica']);
        $this->postJson('/api/catalogos/materias', ['clave' => 'B0101', 'nombre' => 'Otra'])->assertUnprocessable();
        $this->postJson('/api/catalogos/grados', ['nombre' => '7'])->assertCreated();
        $this->postJson('/api/catalogos/planteles', ['clave' => '07ECB0099Z', 'nombre' => 'Plantel 99'])->assertCreated();

        $periodo = PeriodoEscolar::activo();
        $grado = $this->getJson('/api/catalogos/grados')->assertOk()->json('0.id');
        $grupo = ['grado_id' => $grado, 'plantel_id' => 1, 'periodo_id' => $periodo->id, 'letra' => 'z', 'turno' => 'matutino'];
        $this->postJson('/api/catalogos/grupos', $grupo)->assertCreated()->assertJson(['letra' => 'Z']);
        $this->postJson('/api/catalogos/grupos', $grupo)->assertStatus(409);
        $this->getJson("/api/catalogos/grupos?periodo_id={$periodo->id}")->assertOk()->assertJsonCount(4);

        // Un periodo nuevo activo cierra el anterior: siempre uno solo activo
        $this->postJson('/api/catalogos/periodos-escolares', ['nombre' => '2027 A', 'fecha_inicio' => '2027-02-01', 'fecha_fin' => '2027-07-15'])->assertCreated();
        $this->assertSame('cerrado', $periodo->fresh()->estado);
        $this->assertSame(1, PeriodoEscolar::where('estado', 'activo')->count());
        $this->postJson('/api/catalogos/periodos-escolares', ['nombre' => '2027 B', 'fecha_inicio' => '2027-08-01', 'fecha_fin' => '2027-01-01'])
            ->assertUnprocessable()->assertJsonValidationErrors('fecha_fin');
    }

    public function test_padres_tutores_y_vinculo(): void
    {
        $this->como('admin');
        $this->postJson('/api/padres-tutores', ['nombre' => 'Sin contacto'])->assertUnprocessable()->assertJsonValidationErrors('telefono');
        $this->postJson('/api/padres-tutores', ['nombre' => 'X', 'canal_preferido' => 'push', 'telefono' => '9611234567'])->assertJsonValidationErrors('canal_preferido');
        $this->postJson('/api/padres-tutores', ['nombre' => 'X', 'canal_preferido' => 'email'])->assertJsonValidationErrors('correo');

        $tutor = $this->postJson('/api/padres-tutores', ['nombre' => 'Rosa Pérez', 'telefono' => '961 123 4567'])
            ->assertCreated()->assertJson(['canal_preferido' => 'sms'])->json('id');
        $this->putJson("/api/padres-tutores/{$tutor}", ['nombre' => 'Rosa Pérez', 'canal_preferido' => 'email', 'correo' => 'rosa@example.com'])
            ->assertOk()->assertJson(['canal_preferido' => 'email']);
        $this->getJson('/api/padres-tutores?buscar=rosa@')->assertOk()->assertJsonCount(1);

        $alumno = Alumno::has('padresTutores')->first();
        $antes = $alumno->padresTutores()->count();
        $this->postJson("/api/alumnos/{$alumno->id}/padres", ['padre_tutor_id' => $tutor, 'parentesco' => 'abuela', 'es_principal' => true])->assertCreated();
        $this->postJson("/api/alumnos/{$alumno->id}/padres", ['padre_tutor_id' => $tutor])->assertStatus(409);

        $lista = $this->getJson("/api/alumnos/{$alumno->id}/padres")->assertOk()->assertJsonCount($antes + 1)->json();
        $this->assertSame([$tutor], array_column(array_filter($lista, fn ($p) => $p['es_principal']), 'id'));

        $this->deleteJson("/api/alumnos/{$alumno->id}/padres/{$tutor}")->assertNoContent();
        $this->deleteJson("/api/alumnos/{$alumno->id}/padres/{$tutor}")->assertNotFound();
        $this->assertNotNull(PadreTutor::find($tutor));
    }

    public function test_buscar_alumnos(): void
    {
        $alumno = Alumno::first();
        $r = $this->como('orientador1')->getJson('/api/alumnos?matricula='.substr($alumno->matricula, 0, 14))->assertOk()->json();
        $this->assertSame($alumno->id, $r[0]['id']);
        $this->assertNotNull($r[0]['situacion_academica']);
        $this->como('docente1')->getJson('/api/alumnos')->assertForbidden();
    }

    public function test_umbrales_y_configuraciones(): void
    {
        $this->como('admin');
        $u = $this->getJson('/api/umbrales-riesgo')->assertOk()->assertJsonCount(3)->json();
        $this->assertSame(['bajo', 'medio', 'alto'], array_column($u, 'nivel'));

        // Hueco: alto empieza en 20 pero medio termina en 25
        $this->putJson('/api/umbrales-riesgo', ['umbrales' => [
            ['id' => $u[0]['id'], 'porcentaje_min' => 0, 'porcentaje_max' => 8],
            ['id' => $u[1]['id'], 'porcentaje_min' => 8, 'porcentaje_max' => 25],
            ['id' => $u[2]['id'], 'porcentaje_min' => 20, 'porcentaje_max' => 100],
        ]])->assertUnprocessable()->assertJsonValidationErrors('umbrales');

        $this->putJson('/api/umbrales-riesgo', ['umbrales' => [
            ['id' => $u[2]['id'], 'porcentaje_min' => 25, 'porcentaje_max' => 100, 'faltas_consecutivas' => 3],
            ['id' => $u[0]['id'], 'porcentaje_min' => 0, 'porcentaje_max' => 8],
            ['id' => $u[1]['id'], 'porcentaje_min' => 8, 'porcentaje_max' => 25],
        ]])->assertOk()->assertJsonPath('2.porcentaje_min', 25)->assertJsonPath('2.faltas_consecutivas', 3);
        $this->assertSame(8.0, UmbralRiesgo::find($u[0]['id'])->porcentaje_max);

        $this->putJson('/api/configuraciones', ['clave' => 'escala_calificacion', 'valor' => '100'])->assertOk()->assertJson(['valor' => '100']);
        $this->putJson('/api/configuraciones', ['clave' => 'escala_calificacion', 'valor' => '50'])->assertJsonValidationErrors('valor');
        $this->putJson('/api/configuraciones', ['clave' => 'zona_horaria', 'valor' => 'Marte/Olympus'])->assertJsonValidationErrors('valor');
        $this->putJson('/api/configuraciones', ['clave' => 'no_existe', 'valor' => '1'])->assertJsonValidationErrors('clave');
        $this->getJson('/api/configuraciones')->assertOk()->assertJsonCount(3);

        $this->como('orientador1')->getJson('/api/configuraciones')->assertForbidden();
    }
}
