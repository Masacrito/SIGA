<?php

namespace Database\Seeders;

use App\Models\Permiso;
use Illuminate\Database\Seeder;

class PermisoSeeder extends Seeder
{
    /** Slugs definitivos: uno por cada x-permission de openapi.yaml v1.3.0. */
    public const PERMISOS = [
        'gestionar_usuarios' => 'Alta, edición y baja de usuarios; roles, permisos y grupos de orientador',
        'gestionar_catalogos' => 'Planteles, periodos, grados, grupos, materias y padres/tutores',
        'gestionar_configuracion' => 'Umbrales del semáforo y configuraciones generales',
        'registrar_asistencia' => 'Pasar lista (online y sincronización offline)',
        'consultar_asistencias' => 'Consultar historial de asistencias',
        'autorizar_justificacion' => 'Registrar y autorizar justificantes',
        'ver_semaforo_riesgo' => 'Consultar el semáforo de riesgo',
        'registrar_observacion' => 'Bitácora de seguimiento tutorial',
        'notificar_tutor' => 'Enviar y consultar notificaciones a padres/tutores',
        'generar_reportes' => 'Generar reportes PDF/Excel',
        'consultar_alumnos' => 'Buscar y listar alumnos',
        'consultar_calificaciones' => 'Consultar calificaciones',
        'editar_calificaciones' => 'Corregir calificaciones manualmente',
        'importar_datos' => 'Ingesta masiva de archivos',
        'ejecutar_alertas' => 'Disparar manualmente la detección de riesgo',
    ];

    public function run(): void
    {
        foreach (self::PERMISOS as $nombre => $descripcion) {
            Permiso::updateOrCreate(['nombre' => $nombre], ['descripcion' => $descripcion]);
        }
    }
}
