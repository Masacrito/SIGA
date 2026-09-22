<?php

namespace Database\Seeders;

use App\Models\Permiso;
use App\Models\Rol;
use Illuminate\Database\Seeder;

class RolSeeder extends Seeder
{
    /** Matriz rol → permisos (sección 7.2 del checkpoint). '*' = todos. */
    public const MATRIZ = [
        'docente' => [
            'descripcion' => 'Pasa lista y consulta el historial y calificaciones de sus grupos',
            'permisos' => ['registrar_asistencia', 'consultar_asistencias', 'consultar_calificaciones'],
        ],
        'orientador' => [
            'descripcion' => 'Seguimiento tutorial de los grupos que tiene asignados',
            'permisos' => [
                'consultar_asistencias', 'consultar_calificaciones', 'consultar_alumnos',
                'ver_semaforo_riesgo', 'registrar_observacion', 'notificar_tutor',
                'autorizar_justificacion', 'generar_reportes',
            ],
        ],
        'administrador' => [
            'descripcion' => 'Administración completa del sistema',
            'permisos' => '*',
        ],
    ];

    public function run(): void
    {
        foreach (self::MATRIZ as $nombre => $datos) {
            $rol = Rol::updateOrCreate(['nombre' => $nombre], ['descripcion' => $datos['descripcion']]);

            $ids = $datos['permisos'] === '*'
                ? Permiso::pluck('id')
                : Permiso::whereIn('nombre', $datos['permisos'])->pluck('id');

            // sync() deja exactamente la matriz. En producción, si se editan
            // permisos desde PUT /roles/{id}/permisos, considerar
            // syncWithoutDetaching() (pendiente 7.6 punto 5).
            $rol->permisos()->sync($ids);
        }
    }
}
