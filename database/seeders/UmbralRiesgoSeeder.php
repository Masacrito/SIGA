<?php

namespace Database\Seeders;

use App\Models\UmbralRiesgo;
use Illuminate\Database\Seeder;

class UmbralRiesgoSeeder extends Seeder
{
    /**
     * Umbrales globales (periodo_id = NULL). Un alumno cae en el nivel donde
     * porcentaje_min <= %inasistencia < porcentaje_max; 'alto' incluye el 100.
     * faltas_consecutivas queda en NULL hasta que se decida (pendiente 7.6).
     */
    public function run(): void
    {
        $niveles = [
            ['nivel' => 'bajo', 'porcentaje_min' => 0, 'porcentaje_max' => 10, 'color_hex' => '#16a34a'],
            ['nivel' => 'medio', 'porcentaje_min' => 10, 'porcentaje_max' => 20, 'color_hex' => '#f59e0b'],
            ['nivel' => 'alto', 'porcentaje_min' => 20, 'porcentaje_max' => 100, 'color_hex' => '#dc2626'],
        ];

        foreach ($niveles as $n) {
            // Ojo: MySQL permite varios NULL en un UNIQUE, así que la llave
            // (periodo_id, nivel) no protege los globales; lo hace este updateOrCreate.
            UmbralRiesgo::updateOrCreate(
                ['periodo_id' => null, 'nivel' => $n['nivel']],
                $n + ['faltas_consecutivas' => null, 'activo' => true],
            );
        }
    }
}
