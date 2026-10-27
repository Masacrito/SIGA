<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\Asistencia;
use App\Models\Calificacion;
use App\Models\Configuracion;
use App\Models\Grupo;
use App\Models\Materia;
use App\Models\Usuario;
use App\Support\Semaforo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Paso 4: observaciones, justificantes, calificaciones y reportes. Datos del PruebasSeeder. */
class SeguimientoTest extends TestCase
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

    private function alumnoDe(string $grado, string $letra): Alumno
    {
        return $this->grupo($grado, $letra)->alumnos()->where('activo', true)->orderBy('alumnos.id')->firstOrFail();
    }

    public function test_observaciones_por_pertenencia(): void
    {
        $de1A = $this->alumnoDe('1', 'A');
        $de3A = $this->alumnoDe('3', 'A');

        $o = $this->como('orientador1')->postJson('/api/observaciones', ['alumno_id' => $de1A->id, 'texto' => '  Se citó a la madre.  '])
            ->assertCreated()
            ->assertJson(['alumno_id' => $de1A->id, 'texto' => 'Se citó a la madre.', 'fecha' => today()->toDateString(), 'orientador' => ['nombre' => Usuario::where('username', 'orientador1')->value('nombre')]])
            ->json();

        $this->getJson("/api/observaciones?alumno_id={$de1A->id}")->assertOk()->assertJsonPath('0.id', $o['id']);
        $recientes = collect($this->getJson('/api/observaciones')->assertOk()->json());
        $this->assertContains($o['id'], $recientes->pluck('id'));
        $this->assertNotContains($de3A->id, $recientes->pluck('alumno_id'), 'orientador1 no orienta 3A');

        // orientador1 no ve al de 3A: ni escribir ni leer su bitácora
        $this->postJson('/api/observaciones', ['alumno_id' => $de3A->id, 'texto' => 'x'])->assertForbidden();
        $this->getJson("/api/observaciones?alumno_id={$de3A->id}")->assertForbidden();
        $this->postJson('/api/observaciones', ['alumno_id' => $de1A->id])->assertUnprocessable()->assertJsonValidationErrors('texto');

        // el docente no tiene el permiso
        $this->como('docente1')->getJson('/api/observaciones')->assertForbidden();
    }

    public function test_justificante_cambia_la_falta_y_baja_el_porcentaje(): void
    {
        $falta = Asistencia::where('estado', 'ausente')->whereDoesntHave('justificacion')
            ->whereHas('sesion.horario.grupo', fn ($q) => $q->whereKey($this->grupo('1', 'A')->id))->firstOrFail();
        $periodo = $this->grupo('1', 'A')->periodo_id;
        $antes = Semaforo::calcular([$falta->alumno_id], $periodo)[$falta->alumno_id];

        $this->como('orientador2')->postJson("/api/asistencias/{$falta->id}/justificacion", ['motivo' => 'Consulta médica'])->assertForbidden();

        $this->como('orientador1')->postJson("/api/asistencias/{$falta->id}/justificacion", ['motivo' => 'Consulta médica', 'evidencia_url' => 'no-es-url'])
            ->assertUnprocessable()->assertJsonValidationErrors('evidencia_url');
        $this->postJson("/api/asistencias/{$falta->id}/justificacion", ['motivo' => ' Consulta médica (IMSS) '])
            ->assertCreated()->assertJson(['asistencia_id' => $falta->id, 'motivo' => 'Consulta médica (IMSS)', 'estado' => 'justificado']);

        $this->assertSame('justificado', $falta->fresh()->estado);
        $despues = Semaforo::calcular([$falta->alumno_id], $periodo)[$falta->alumno_id];
        $this->assertSame($antes['clases'], $despues['clases'], 'sigue contando como clase');
        $this->assertSame($antes['ausencias'] - 1, $despues['ausencias']);

        // dos veces no; sobre una asistencia "presente", tampoco
        $this->postJson("/api/asistencias/{$falta->id}/justificacion", ['motivo' => 'otra vez'])->assertConflict();
        $presente = Asistencia::where('estado', 'presente')->where('alumno_id', $falta->alumno_id)->firstOrFail();
        $this->postJson("/api/asistencias/{$presente->id}/justificacion", ['motivo' => 'x'])->assertUnprocessable();

        // el historial ya trae el motivo
        $this->getJson("/api/asistencias/historial?alumno_id={$falta->alumno_id}")->assertOk()
            ->assertJsonFragment(['id' => $falta->id, 'estado' => 'justificado', 'motivo_justificacion' => 'Consulta médica (IMSS)']);
    }

    public function test_calificaciones_consulta_y_correccion_segun_escala(): void
    {
        $alumno = $this->alumnoDe('1', 'A');
        $boleta = $this->como('docente1')->getJson("/api/alumnos/{$alumno->id}/calificaciones")->assertOk()->json();
        $this->assertNotEmpty($boleta);
        $this->assertArrayHasKey('materia', $boleta[0]);
        $this->como('docente1')->getJson('/api/alumnos/'.$this->alumnoDe('3', 'A')->id.'/calificaciones')->assertForbidden();

        $numerica = Calificacion::where('alumno_id', $alumno->id)->whereRelation('materia', 'tipo_evaluacion', 'numerica')->firstOrFail();
        // Orientación Educativa (A/NA) no tiene horario en el PruebasSeeder: se le crea una
        $alfa = Calificacion::create([
            'alumno_id' => $alumno->id, 'materia_id' => Materia::where('clave', 'OE')->value('id'),
            'periodo_id' => $numerica->periodo_id, 'parcial' => 1, 'calificacion' => 'A',
        ]);

        $this->como('docente1')->putJson("/api/calificaciones/{$numerica->id}", ['calificacion' => '9'])->assertForbidden();

        $this->como('admin');
        foreach (['11', '8.55', 'diez', '-1'] as $mala) {
            $this->putJson("/api/calificaciones/{$numerica->id}", ['calificacion' => $mala])->assertUnprocessable()->assertJsonValidationErrors('calificacion');
        }
        $this->putJson("/api/calificaciones/{$numerica->id}", ['calificacion' => '08.0'])->assertOk()
            ->assertJson(['calificacion' => '8', 'origen' => 'manual', 'editado_por' => ['nombre' => Usuario::where('username', 'admin')->value('nombre')]]);

        $this->putJson("/api/calificaciones/{$alfa->id}", ['calificacion' => '9'])->assertUnprocessable();
        $this->putJson("/api/calificaciones/{$alfa->id}", ['calificacion' => 'na'])->assertOk()->assertJson(['calificacion' => 'NA']);

        // con escala 100, 95 ya es válido
        Configuracion::where('clave', 'escala_calificacion')->update(['valor' => '100']);
        $this->putJson("/api/calificaciones/{$numerica->id}", ['calificacion' => '95'])->assertOk()->assertJson(['calificacion' => '95']);
    }

    public function test_reporte_pdf_y_excel_del_grupo(): void
    {
        $g = $this->grupo('1', 'A');

        $pdf = $this->como('orientador1')->get("/api/reportes?grupo_id={$g->id}")->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-1.4', $pdf->getContent());
        $this->assertStringContainsString('attachment; filename="SIGA_asistencia_1a_matutino_2026_b.pdf"', $pdf->headers->get('Content-Disposition'));
        $this->assertStringContainsString('/Count ', $pdf->getContent());

        $xlsx = $this->get("/api/reportes?grupo_id={$g->id}&formato=excel")->assertOk()->getContent();
        $this->assertStringStartsWith("PK\x03\x04", $xlsx);
        $archivo = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($archivo, $xlsx);
        if (class_exists(\ZipArchive::class)) {
            $zip = new \ZipArchive;
            $this->assertTrue($zip->open($archivo) === true, 'el ZIP abre');
            $hoja = $zip->getFromName('xl/worksheets/sheet1.xml');
            $this->assertNotFalse(simplexml_load_string($hoja), 'XML válido');
            $activos = $g->alumnos()->where('activo', true)->count();
            $this->assertSame(substr_count($hoja, '<row ') - 9, $activos, 'una fila por alumno activo (+ título, 2 líneas, vacía y encabezado arriba; vacía y 3 de pie abajo)');
        }

        $this->getJson('/api/reportes')->assertUnprocessable()->assertJsonValidationErrors('grupo_id');
        $this->getJson('/api/reportes?grupo_id='.$this->grupo('3', 'A')->id)->assertForbidden();
        $this->como('docente1')->getJson("/api/reportes?grupo_id={$g->id}")->assertForbidden();
    }
}
