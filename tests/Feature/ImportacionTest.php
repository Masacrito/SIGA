<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\Calificacion;
use App\Models\Docente;
use App\Models\Grupo;
use App\Models\Horario;
use App\Models\Importacion;
use App\Models\Materia;
use App\Models\PadreTutor;
use App\Models\PeriodoEscolar;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

/** Paso 3: plantillas de Excel e importación todo o nada. Datos del PruebasSeeder. */
class ImportacionTest extends TestCase
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

    /** Descarga la plantilla y la abre como la abriría Excel. */
    private function plantilla(array $query): Spreadsheet
    {
        $r = $this->get('/api/importaciones/plantilla?'.http_build_query($query))->assertOk();
        $this->assertStringContainsString('spreadsheetml', $r->headers->get('Content-Type'));
        $ruta = tempnam(sys_get_temp_dir(), 'siga').'.xlsx';
        file_put_contents($ruta, $r->streamedContent());

        return IOFactory::load($ruta);
    }

    private function subir(Spreadsheet $libro, string $nombre = 'datos.xlsx')
    {
        $ruta = tempnam(sys_get_temp_dir(), 'siga').'.xlsx';
        IOFactory::createWriter($libro, 'Xlsx')->save($ruta);

        return $this->post('/api/importaciones', ['archivo' => new UploadedFile($ruta, $nombre, null, null, true)], ['Accept' => 'application/json']);
    }

    public function test_alumnos_plantilla_carga_y_reimportar_no_duplica(): void
    {
        $libro = $this->como('admin')->plantilla(['tipo' => 'alumnos']);
        $datos = $libro->getSheetByName('Datos');
        $this->assertSame('Matrícula', $datos->getCell('A1')->getValue());
        $this->assertContains('1A matutino', array_column($libro->getSheetByName('Listas')->toArray(), 0));
        $this->assertSame('@', $libro->getCellXfByIndex($datos->getColumnDimension('A')->getXfIndex())->getNumberFormat()->getFormatCode(), 'matrícula como texto en toda la columna');
        $this->assertSame('Listas!$A$1:$A$3', $datos->getDataValidation('E2')->getFormula1());

        $datos->fromArray([
            ['26071035009901', 'Gómez', 'Pérez', 'Ana Sofía', '1A matutino', 'irregular', 'Pérez López María', 'Madre', '961 123 4567', '', ''],
            ['26071035009902', 'Gómez', 'Pérez', '  Luis   Ángel ', '3a VESPERTINO', '', 'pérez lópez maría', 'Madre', '961 123 4567', '', ''],
            ['26071035009903', 'Ruiz', '', 'Mateo', '1B matutino', '', '', '', '', '', ''],
        ], null, 'A2', true);
        $datos->setCellValue('A3', 26071035009902.0); // matrícula capturada como número: se lee igual

        $r = $this->subir($libro, 'alumnos.xlsx')->assertCreated()
            ->assertJson(['tipo' => 'alumnos', 'estado' => 'completado', 'filas_procesadas' => 3, 'filas_error' => 0, 'errores' => [], 'usuario' => ['nombre' => Usuario::where('username', 'admin')->value('nombre')]]);

        $ana = Alumno::where('matricula', '26071035009901')->firstOrFail();
        $this->assertSame('Gómez Pérez Ana Sofía', $ana->nombre);
        $this->assertSame('irregular', $ana->inscripciones()->value('situacion_academica'));
        $this->assertSame('Gómez Pérez Luis Ángel', Alumno::where('matricula', '26071035009902')->value('nombre'));
        $this->assertSame($this->grupo('3', 'A')->id, Alumno::where('matricula', '26071035009902')->firstOrFail()->inscripciones()->value('grupo_id'));

        // Hermanos: un solo tutor, principal de los dos
        $tutor = PadreTutor::where('telefono', '961 123 4567')->sole();
        $this->assertSame(2, $tutor->alumnos()->wherePivot('es_principal', true)->wherePivot('parentesco', 'Madre')->count());
        $this->assertSame(0, Alumno::where('matricula', '26071035009903')->firstOrFail()->padresTutores()->count());

        // Reimportar con un cambio de grupo: actualiza, no duplica
        $antes = [Alumno::count(), PadreTutor::count()];
        $datos->setCellValue('E2', '1B matutino');
        $this->subir($libro)->assertCreated()->assertJson(['estado' => 'completado']);
        $this->assertSame($antes, [Alumno::count(), PadreTutor::count()]);
        $this->assertSame($this->grupo('1', 'B')->id, $ana->inscripciones()->value('grupo_id'));

        $this->getJson('/api/importaciones')->assertOk()->assertJsonCount(3)->assertJsonPath('0.id', $r->json('id') + 1);
    }

    public function test_alumnos_todo_o_nada_con_errores_por_fila_y_columna(): void
    {
        $libro = $this->como('admin')->plantilla(['tipo' => 'alumnos']);
        $libro->getSheetByName('Datos')->fromArray([
            ['26071035009911', 'Bien', '', 'Escrita', '1A matutino', '', '', '', '', '', ''],
            ['26071035009911', 'Repetida', '', 'Fila', '1A matutino', '', '', '', '', '', ''],
            ['26071035009912', '', '', 'Sin paterno', '4Z matutino', 'becado', '', '', '', '', ''],
            ['26071035009913', 'Con', '', 'Tutor', '1A matutino', '', '', 'Madre', '', '', 'sms'],
        ], null, 'A2', true);
        $alumnos = Alumno::count();

        $r = $this->subir($libro)->assertCreated()->assertJson(['estado' => 'con_errores', 'filas_procesadas' => 4, 'filas_error' => 3]);
        $this->assertSame([
            [3, 'Matrícula', 'La matrícula 26071035009911 ya está en la fila 2.'],
            [4, 'Apellido paterno', 'Falta el apellido paterno.'],
            [4, 'Grupo', 'El grupo "4Z matutino" no existe en el periodo activo. Elígelo de la lista.'],
            [4, 'Situación académica', 'La situación debe ser regular, irregular, recursador o extemporaneo.'],
            [5, 'Tutor: nombre completo', 'Falta el nombre del tutor (lo pide porque llenaste otros datos del tutor).'],
            [5, 'Tutor: teléfono', 'Con canal sms se necesita el teléfono del tutor.'],
        ], array_map(fn ($e) => [$e['fila'], $e['columna'], $e['mensaje']], $r->json('errores')));
        $this->assertSame($alumnos, Alumno::count(), 'con un error no se guarda ninguna fila');

        $this->getJson('/api/importaciones/'.$r->json('id'))->assertOk()->assertJsonCount(6, 'errores');
    }

    public function test_calificaciones_por_grupo_y_parcial(): void
    {
        $a1 = $this->grupo('1', 'A');
        $a1->alumnos()->where('activo', true)->orderBy('nombre')->firstOrFail()->update(['nombre' => '=HYPERLINK("http://x","clic")']);
        $libro = $this->como('admin')->plantilla(['tipo' => 'calificaciones', 'grupo_id' => $a1->id, 'parcial' => 1]);
        $datos = $libro->getSheetByName('Datos');
        $filas = $datos->rangeToArray('A1:'.$datos->getHighestDataColumn().$datos->getHighestDataRow(), null, false, false);

        $this->assertSame('s', $datos->getCell('B2')->getDataType(), 'un nombre que empieza con = queda como texto, no como fórmula');
        $alumnosActivos = $a1->alumnos()->where('activo', true)->count();
        $this->assertCount($alumnosActivos + 1, $filas, 'trae la lista del grupo, sin bajas');
        $materia = Materia::where('tipo_evaluacion', 'numerica')
            ->whereIn('id', Calificacion::whereIn('alumno_id', $a1->alumnos()->select('alumnos.id'))->select('materia_id'))->orderBy('nombre')->firstOrFail();
        $col = array_search("{$materia->nombre} ({$materia->clave})", $filas[0], true);
        $this->assertNotFalse($col);

        $matricula = $filas[1][0];
        $alumno = Alumno::where('matricula', $matricula)->firstOrFail();
        $previa = Calificacion::where(['alumno_id' => $alumno->id, 'materia_id' => $materia->id, 'parcial' => 1])->firstOrFail();
        $this->assertEquals($previa->calificacion, $filas[1][$col], 'viene lo ya capturado');

        // Una corrección manual hecha ANTES de descargar se respeta si no se toca
        $manual = Calificacion::where('origen', 'manual')->whereIn('alumno_id', $a1->alumnos()->select('alumnos.id'))->where('parcial', 1)->first();

        $letra = Coordinate::stringFromColumnIndex($col + 1);
        $datos->setCellValue("{$letra}2", 7.5);
        $this->subir($libro)->assertCreated()->assertJson(['tipo' => 'calificaciones', 'estado' => 'completado', 'filas_procesadas' => $alumnosActivos]);
        $this->assertSame(['7.5', 'importado', null], array_values($previa->fresh()->only(['calificacion', 'origen', 'editado_por'])));
        if ($manual) {
            $this->assertSame('manual', $manual->fresh()->origen);
        }

        // Después de descargar, alguien la corrige en SIGA: subir el archivo viejo no la pisa
        $this->travel(1)->minutes();
        $this->putJson("/api/calificaciones/{$previa->id}", ['calificacion' => '9'])->assertOk();
        $datos->setCellValue("{$letra}2", 6);
        $datos->setCellValue("{$letra}3", 11);
        $r = $this->subir($libro)->assertCreated()->assertJson(['estado' => 'con_errores', 'filas_error' => 2]);
        $this->assertStringContainsString('ahora vale 9', $r->json('errores.0.mensaje'));
        $this->assertSame([null, 3, $materia->nombre, 'La calificación debe ser un número de 0 a 10, con un decimal como máximo.'], array_values($r->json('errores.1')));
        $this->assertSame('9', $previa->fresh()->calificacion);
    }

    public function test_archivos_que_no_son_plantilla_y_permisos(): void
    {
        $registradas = Importacion::count();
        $ajeno = new Spreadsheet;
        $ajeno->getActiveSheet()->fromArray([['Matrícula', 'Nombre'], ['1', 'X']]);
        $this->como('admin')->subir($ajeno)->assertUnprocessable()->assertJsonValidationErrors(['archivo' => 'no es una plantilla de SIGA']);

        $vacia = $this->plantilla(['tipo' => 'alumnos']);
        $this->subir($vacia)->assertUnprocessable()->assertJsonValidationErrors(['archivo' => 'vacía']);

        $movida = $this->plantilla(['tipo' => 'alumnos']);
        $movida->getSheetByName('Datos')->insertNewColumnBefore('A')->fromArray([['Extra'], ['x']]);
        $this->subir($movida)->assertUnprocessable()->assertJsonValidationErrors(['archivo' => 'columnas']);

        $this->post('/api/importaciones', ['archivo' => UploadedFile::fake()->create('lista.csv', 1)], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors(['archivo' => '.xlsx']);
        $this->getJson('/api/importaciones/plantilla?tipo=calificaciones')->assertUnprocessable()->assertJsonValidationErrors(['grupo_id', 'parcial']);
        $this->assertSame($registradas, Importacion::count(), 'lo que no es plantilla no se registra');

        $this->como('docente1')->getJson('/api/importaciones/plantilla?tipo=alumnos')->assertForbidden();
        $this->getJson('/api/importaciones')->assertForbidden();
    }

    /** "Nombre (CLAVE)" como sale en la lista de materias de la plantilla. */
    private function materia(string $clave): string
    {
        $m = Materia::where('clave', $clave)->firstOrFail();

        return "{$m->nombre} ({$m->clave})";
    }

    public function test_docentes_y_orientadores_con_clases_credenciales_y_reimportar(): void
    {
        // orientador1 ya existe (orienta 1A y 1B) y además siguió a un grupo de un periodo anterior
        $orientador1 = Usuario::where('username', 'orientador1')->firstOrFail();
        Docente::create(['usuario_id' => $orientador1->id, 'numero_empleado' => 'O-01']);
        $anterior = $this->grupo('1', 'A')->replicate()->fill([
            'periodo_id' => PeriodoEscolar::create(['nombre' => '2026 A', 'estado' => 'cerrado'])->id,
        ]);
        $anterior->save();
        $orientador1->gruposOrientados()->attach($anterior->id);

        $libro = $this->como('admin')->plantilla(['tipo' => 'docentes']);
        $personal = $libro->getSheetByName('Personal');
        $clases = $libro->getSheetByName('Clases');
        $this->assertSame('Listas!$B$1:$B$3', $clases->getDataValidation('B2')->getFormula1(), 'grupos del periodo activo');
        $this->assertSame(['Número de empleado', 'Grupo', 'Materia', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'], $clases->toArray()[0]);

        $personal->fromArray([
            ['01', 'Ruiz', 'Vázquez', 'Luis Alberto', 'docente', 'luis@cobach35.test', ''],
            ['O-07', 'Pérez', '', 'Ana', 'Orientador', '', '1A matutino, 3a vespertino'],
            ['D-0001', 'Méndez', 'Ruiz', 'Juan Carlos', 'docente', '', ''], // ya existe (docente1): no se duplica
            ['O-01', 'Cruz', 'Gómez', 'María Elena', 'orientador', '', '3A vespertino'],
        ], null, 'A2', true);
        $clases->fromArray([
            ['01', '1A matutino', $this->materia('A0023'), '9:00-9:50', '', '09:00 - 09:50', '', '', ''],
            // Ya existen en SIGA: se actualiza quién las da (el orientador da OE; docente2 no está en la hoja Personal)
            ['O-07', '3A vespertino', $this->materia('OE'), '', '', '', '', '15:00-15:50', ''],
            ['D-0002', '1B matutino', $this->materia('A0022'), '', '08:50-09:40', '', '', '', ''],
        ], null, 'A2', true);
        $usuarios = Usuario::count();
        $horarios = Horario::count();

        $r = $this->subir($libro, 'personal.xlsx')->assertCreated()
            ->assertJson(['tipo' => 'docentes', 'estado' => 'completado', 'filas_procesadas' => 7, 'errores' => []]);
        $this->assertSame(['LARVDOC01', 'APORIO-07'], array_column($r->json('credenciales'), 'usuario'));
        $this->assertMatchesRegularExpression('/^[A-HJ-NP-Za-hj-km-z2-9]{10}$/', $r->json('credenciales.0.password'));
        $this->getJson('/api/importaciones/'.$r->json('id'))->assertOk()->assertJsonMissingPath('credenciales');

        $luis = Usuario::where('username', 'LARVDOC01')->firstOrFail();
        $this->assertSame(['Ruiz Vázquez Luis Alberto', 'docente', true, '01'], [$luis->nombre, $luis->rol->nombre, $luis->debe_cambiar_password, $luis->docente->numero_empleado]);
        $this->assertTrue(Hash::check($r->json('credenciales.0.password'), $luis->password));
        $this->assertSame(['09:00:00', '09:00:00'], $luis->docente->horarios()->orderBy('dia_semana')->pluck('hora_inicio')->all());

        $ana = Usuario::where('username', 'APORIO-07')->firstOrFail();
        $this->assertEqualsCanonicalizing([$this->grupo('1', 'A')->id, $this->grupo('3', 'A')->id], $ana->gruposOrientados()->pluck('grupos.id')->all());
        $this->assertSame(1, $ana->docente->horarios()->where('dia_semana', 5)->count(), 'el orientador da su clase');

        $this->assertEqualsCanonicalizing([$anterior->id, $this->grupo('3', 'A')->id], $orientador1->gruposOrientados()->pluck('grupos.id')->all(),
            'la lista reemplaza sus grupos de este periodo; el del periodo anterior se queda');

        $docente1 = Usuario::where('username', 'docente1')->firstOrFail();
        $this->assertSame(['docente1@cobach35.test', false], [$docente1->correo, $docente1->debe_cambiar_password], 'correo vacío no borra; no se le cambia la contraseña');
        $this->assertSame(Usuario::where('username', 'docente2')->firstOrFail()->docente->id,
            Horario::where(['grupo_id' => $this->grupo('1', 'B')->id, 'dia_semana' => 2])->value('docente_id'));
        $this->assertSame([$usuarios + 2, $horarios + 2], [Usuario::count(), Horario::count()]);

        // Volver a subir el mismo archivo: nada nuevo, ninguna contraseña
        $this->subir($libro)->assertCreated()->assertJson(['estado' => 'completado', 'credenciales' => []]);
        $this->assertSame([$usuarios + 2, $horarios + 2], [Usuario::count(), Horario::count()]);
        $this->assertTrue(Hash::check($r->json('credenciales.0.password'), $luis->fresh()->password));
    }

    public function test_docentes_todo_o_nada_con_hoja_fila_y_columna(): void
    {
        $admin = Usuario::where('username', 'admin')->firstOrFail();
        Docente::create(['usuario_id' => $admin->id, 'numero_empleado' => 'ADM1']);

        $libro = $this->como('admin')->plantilla(['tipo' => 'docentes']);
        $libro->getSheetByName('Personal')->fromArray([
            ['20', 'Bien', '', 'Escrita', 'docente', '', ''],
            ['20', 'Repetida', '', 'Fila', 'docente', '', ''],
            ['21', 'Sin', '', 'Rol', 'director', 'no-es-correo', ''],
            ['22', 'Docente', '', 'Con Grupos', 'docente', '', '1A matutino'],
            ['23', 'Orientadora', '', 'Mal Grupo', 'orientador', '', '1A matutino; 9Z nocturno'],
            ['ADM1', 'Cuenta', '', 'Admin', 'docente', '', ''],
        ], null, 'A2', true);
        $libro->getSheetByName('Clases')->fromArray([
            ['X99', '1A matutino', $this->materia('A0023'), '12:00-12:50', '', '', '', '', ''],
            ['20', '4Z matutino', 'Alquimia (X1)', '7-8', '', '', '', '', ''],
            ['20', '1A matutino', $this->materia('A0023'), '', '', '', '', '', ''],
            ['20', '1A matutino', $this->materia('A0022'), '07:30-08:20', '', '', '', '', ''], // 1A ya tiene clase el lunes 07:00–07:50
            ['D-0001', '3A vespertino', $this->materia('A0022'), '07:00-07:50', '', '', '', '', ''], // docente1 ya da clase a esa hora en 1A
            // Persona nueva con dos clases encimadas dentro del mismo archivo
            ['20', '1B matutino', $this->materia('A0023'), '', '', '', '', '', '08:00-08:50'],
            ['20', '3A vespertino', $this->materia('A0022'), '', '', '', '', '', '08:30-09:20'],
        ], null, 'A2', true);
        $antes = [Usuario::count(), Horario::count()];

        $r = $this->subir($libro)->assertCreated()->assertJson(['estado' => 'con_errores', 'filas_procesadas' => 13, 'filas_error' => 12]);
        $errores = array_map(fn ($e) => "{$e['hoja']} {$e['fila']} {$e['columna']}: {$e['mensaje']}", $r->json('errores'));
        foreach ([
            'Personal 3 Número de empleado: El número de empleado 20 ya está en la fila 2.',
            'Personal 4 Rol: El rol debe ser docente u orientador.',
            'Personal 4 Correo: El correo no es válido.',
            'Personal 5 Grupos que orienta: Solo los orientadores tienen grupos que orientar. Deja vacía esta columna o cambia el rol.',
            'Personal 6 Grupos que orienta: El grupo "9Z nocturno" no existe en el periodo activo. Escríbelo como en la lista de la hoja Clases.',
            'Personal 7 Número de empleado: El número de empleado ADM1 es de una cuenta de administración (admin); esas no se cambian por importación.',
            'Clases 2 Número de empleado: El número de empleado X99 no está en la hoja Personal ni existe en SIGA.',
            'Clases 3 Grupo: El grupo "4Z matutino" no existe en el periodo activo. Elígelo de la lista.',
            'Clases 3 Materia: La materia "Alquimia (X1)" no existe. Elígela de la lista.',
            'Clases 3 Lunes: "7-8" no es un horario. Escríbelo como inicio-fin en 24 horas, por ejemplo 07:00-07:50.',
            'Clases 4 Lunes: Escribe la hora de la clase en al menos un día.',
        ] as $esperado) {
            $this->assertContains($esperado, $errores);
        }
        $this->assertCount(1, preg_grep('/^Clases 5 Lunes: El grupo ya tiene .+ \(07:00–07:50\), ya en SIGA a esa hora\.$/u', $errores));
        $this->assertCount(1, preg_grep('/^Clases 6 Lunes: Quien da esta clase ya da .+ en 1A matutino a esa hora\.$/u', $errores));
        $this->assertContains('Clases 7 Sábado: Quien da esta clase ya da '.$this->materia('A0022').' (08:30–09:20), fila 8 en 3A vespertino a esa hora.', $errores);
        $this->assertCount(1, preg_grep('/^Clases 8 Sábado: Quien da esta clase ya da .+ fila 7 en 1B matutino a esa hora\.$/u', $errores));
        $this->assertCount(15, $errores);
        $this->assertSame($antes, [Usuario::count(), Horario::count()], 'con un error no se guarda nada');
    }

    public function test_password_temporal_obliga_a_cambiarla(): void
    {
        $usuario = Usuario::factory()->conRol('docente')->create(['username' => 'TEMPDOC09', 'password' => 'Temporal23']);
        $usuario->forceFill(['debe_cambiar_password' => true])->save();

        $login = $this->postJson('/api/login', ['username' => 'TEMPDOC09', 'password' => 'Temporal23'])->assertOk()
            ->assertJsonPath('usuario.debe_cambiar_password', true);
        $otraSesion = $this->postJson('/api/login', ['username' => 'TEMPDOC09', 'password' => 'Temporal23'])->json('token');
        $this->withToken($login->json('token'));

        // Hasta cambiarla solo llega a /me, /logout y /me/password
        $this->getJson('/api/mis-horarios')->assertForbidden()->assertJson(['codigo' => 'debe_cambiar_password']);
        $this->getJson('/api/me')->assertOk();

        $this->putJson('/api/me/password', ['password_actual' => 'otra', 'password' => 'NuevaClave1'])->assertUnprocessable()->assertJsonValidationErrors('password_actual');
        $this->putJson('/api/me/password', ['password_actual' => 'Temporal23', 'password' => 'Temporal23'])->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->putJson('/api/me/password', ['password_actual' => 'Temporal23', 'password' => 'corta'])->assertUnprocessable();
        $this->putJson('/api/me/password', ['password_actual' => 'Temporal23', 'password' => 'NuevaClave1'])->assertOk()
            ->assertJsonPath('debe_cambiar_password', false);

        $this->getJson('/api/mis-horarios')->assertOk();
        $this->assertSame(1, $usuario->tokens()->count(), 'las demás sesiones se cierran');
        $this->app['auth']->forgetGuards();
        $this->withToken($otraSesion)->getJson('/api/me')->assertUnauthorized();
        $this->postJson('/api/login', ['username' => 'TEMPDOC09', 'password' => 'NuevaClave1'])->assertOk();
    }
}
