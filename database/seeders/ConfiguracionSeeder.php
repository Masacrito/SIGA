<?php

namespace Database\Seeders;

use App\Models\Configuracion;
use Illuminate\Database\Seeder;

class ConfiguracionSeeder extends Seeder
{
    public function run(): void
    {
        $configuraciones = [
            ['clave' => 'escala_calificacion', 'valor' => '10', 'tipo_dato' => 'entero',
                'descripcion' => 'Escala activa: 10 o 100 (punto abierto 2)'],
            ['clave' => 'zona_horaria', 'valor' => 'America/Mexico_City', 'tipo_dato' => 'texto',
                'descripcion' => 'Para interpretar capturada_en y agrupar sesiones por día'],
            ['clave' => 'retardos_equivalen_falta', 'valor' => '0', 'tipo_dato' => 'entero',
                'descripcion' => 'Cuántos retardos cuentan como una falta en el semáforo; 0 = no cuentan'],
        ];

        foreach ($configuraciones as $c) {
            // firstOrCreate: si alguien ya cambió el valor desde la API, no se pisa.
            Configuracion::firstOrCreate(['clave' => $c['clave']], $c);
        }
    }
}
