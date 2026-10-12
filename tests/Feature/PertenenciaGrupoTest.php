<?php

namespace Tests\Feature;

use App\Models\Grupo;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Pertenencia: cada quien ve solo sus grupos (datos del DemoSeeder). */
class PertenenciaGrupoTest extends TestCase
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

    public function test_docente_ve_sus_horarios_y_sus_grupos_nada_mas(): void
    {
        $horarios = $this->como('docente1')->getJson('/api/mis-horarios')->assertOk()->json();
        $this->assertNotEmpty($horarios);
        $this->assertEqualsCanonicalizing(['A', 'B'], array_values(array_unique(array_column(array_column($horarios, 'grupo'), 'letra'))));
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $horarios[0]['hora_inicio']);

        $alumnos = $this->getJson('/api/grupos/'.$this->grupo('1', 'A')->id.'/alumnos')->assertOk()->json();
        $this->assertCount(25, $alumnos);
        $this->assertArrayHasKey('situacion_academica', $alumnos[0]);

        $this->getJson('/api/grupos/'.$this->grupo('3', 'A')->id.'/alumnos')->assertForbidden()
            ->assertJson(['message' => 'No tienes permiso para realizar esta acción.']);
    }

    public function test_admin_ve_cualquier_grupo_y_no_tiene_horarios(): void
    {
        $this->como('admin')->getJson('/api/grupos/'.$this->grupo('3', 'A')->id.'/alumnos')->assertOk();
        $this->getJson('/api/mis-horarios')->assertOk()->assertExactJson([]);
    }

    public function test_orientador_sin_permiso_y_grupo_inexistente(): void
    {
        $this->como('orientador1')->getJson('/api/grupos/'.$this->grupo('1', 'A')->id.'/alumnos')->assertForbidden();
        $this->como('docente1')->getJson('/api/grupos/999999/alumnos')->assertNotFound();
    }

    public function test_pertenencia_del_orientador(): void
    {
        $o1 = Usuario::where('username', 'orientador1')->first();
        $this->assertTrue($o1->puedeVerGrupo($this->grupo('1', 'A')));
        $this->assertFalse($o1->puedeVerGrupo($this->grupo('3', 'A')));
    }
}
