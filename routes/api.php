<?php

use App\Http\Controllers\Api;
use Illuminate\Support\Facades\Route;

/*
| Rutas de la API — contrato: openapi.yaml v1.3.0.
| Cada ruta lleva el permiso de su x-permission. Los controladores que aún
| responden 501 se van implementando desde s5a; mientras, móvil y web usan el
| mock de Prism.
*/

Route::post('/login', [Api\AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [Api\AuthController::class, 'logout']);
    Route::get('/me', [Api\AuthController::class, 'me']);
    Route::post('/dispositivos', [Api\DispositivoController::class, 'store']);

    // Docente
    Route::middleware('permiso:registrar_asistencia')->group(function () {
        Route::get('/mis-horarios', [Api\HorarioController::class, 'misHorarios']);
        Route::get('/grupos/{grupo}/alumnos', [Api\GrupoController::class, 'alumnos']);
        Route::post('/sesiones-clase', [Api\SesionClaseController::class, 'store']);
        Route::post('/asistencias', [Api\AsistenciaController::class, 'store']);
        Route::post('/asistencias/lote', [Api\AsistenciaController::class, 'lote']);
        Route::post('/asistencias/sincronizar', [Api\AsistenciaController::class, 'sincronizar']);
    });
    Route::get('/asistencias/historial', [Api\AsistenciaController::class, 'historial'])
        ->middleware('permiso:consultar_asistencias');

    // Orientador
    Route::get('/semaforo-riesgo', [Api\SemaforoController::class, 'index'])->middleware('permiso:ver_semaforo_riesgo');
    Route::get('/reportes', [Api\ReporteController::class, 'generar'])->middleware('permiso:generar_reportes');
    Route::middleware('permiso:registrar_observacion')->group(function () {
        Route::get('/observaciones', [Api\ObservacionController::class, 'index']);
        Route::post('/observaciones', [Api\ObservacionController::class, 'store']);
    });
    Route::middleware('permiso:notificar_tutor')->group(function () {
        Route::get('/notificaciones', [Api\NotificacionController::class, 'index']);
        Route::post('/notificaciones', [Api\NotificacionController::class, 'store']);
    });
    Route::get('/alumnos/{alumno}/calificaciones', [Api\CalificacionController::class, 'porAlumno'])
        ->middleware('permiso:consultar_calificaciones');
    Route::post('/asistencias/{asistencia}/justificacion', [Api\JustificacionController::class, 'store'])
        ->middleware('permiso:autorizar_justificacion');
    Route::get('/alumnos', [Api\AlumnoController::class, 'index'])->middleware('permiso:consultar_alumnos');

    // Administrador
    Route::middleware('permiso:gestionar_usuarios')->group(function () {
        Route::get('/usuarios', [Api\UsuarioController::class, 'index']);
        Route::post('/usuarios', [Api\UsuarioController::class, 'store']);
        Route::put('/usuarios/{usuario}', [Api\UsuarioController::class, 'update']);
        Route::delete('/usuarios/{usuario}', [Api\UsuarioController::class, 'destroy']);
        Route::get('/roles', [Api\RolController::class, 'index']);
        Route::put('/roles/{rol}/permisos', [Api\RolController::class, 'actualizarPermisos']);
        Route::get('/permisos', [Api\PermisoController::class, 'index']);
        Route::get('/orientadores/{usuario}/grupos', [Api\OrientadorGrupoController::class, 'index']);
        Route::post('/orientadores/{usuario}/grupos', [Api\OrientadorGrupoController::class, 'store']);
    });
    Route::middleware('permiso:gestionar_catalogos')->group(function () {
        Route::prefix('catalogos')->group(function () {
            Route::get('/planteles', [Api\Catalogos\PlantelController::class, 'index']);
            Route::post('/planteles', [Api\Catalogos\PlantelController::class, 'store']);
            Route::get('/grados', [Api\Catalogos\GradoController::class, 'index']);
            Route::post('/grados', [Api\Catalogos\GradoController::class, 'store']);
            Route::get('/grupos', [Api\Catalogos\GrupoController::class, 'index']);
            Route::post('/grupos', [Api\Catalogos\GrupoController::class, 'store']);
            Route::get('/materias', [Api\Catalogos\MateriaController::class, 'index']);
            Route::post('/materias', [Api\Catalogos\MateriaController::class, 'store']);
            Route::get('/periodos-escolares', [Api\Catalogos\PeriodoEscolarController::class, 'index']);
            Route::post('/periodos-escolares', [Api\Catalogos\PeriodoEscolarController::class, 'store']);
        });
        Route::get('/padres-tutores', [Api\PadreTutorController::class, 'index']);
        Route::post('/padres-tutores', [Api\PadreTutorController::class, 'store']);
        Route::put('/padres-tutores/{padreTutor}', [Api\PadreTutorController::class, 'update']);
        Route::get('/alumnos/{alumno}/padres', [Api\AlumnoPadreController::class, 'index']);
        Route::post('/alumnos/{alumno}/padres', [Api\AlumnoPadreController::class, 'store']);
        Route::delete('/alumnos/{alumno}/padres/{padreTutor}', [Api\AlumnoPadreController::class, 'destroy']);
    });
    Route::middleware('permiso:gestionar_configuracion')->group(function () {
        Route::get('/umbrales-riesgo', [Api\UmbralRiesgoController::class, 'index']);
        Route::put('/umbrales-riesgo', [Api\UmbralRiesgoController::class, 'update']);
        Route::get('/configuraciones', [Api\ConfiguracionController::class, 'index']);
        Route::put('/configuraciones', [Api\ConfiguracionController::class, 'update']);
    });
    Route::put('/calificaciones/{calificacion}', [Api\CalificacionController::class, 'update'])
        ->middleware('permiso:editar_calificaciones');
    Route::middleware('permiso:importar_datos')->group(function () {
        Route::post('/importaciones', [Api\ImportacionController::class, 'store']);
        Route::get('/importaciones/{importacion}', [Api\ImportacionController::class, 'show']);
    });
    Route::post('/sistema/alertas/ejecutar', [Api\AlertaController::class, 'ejecutar'])
        ->middleware('permiso:ejecutar_alertas');
});
