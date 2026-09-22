<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Catálogos que existen en cualquier entorno, producción incluida.
 * Todos los seeders que llama son idempotentes (updateOrCreate por llave única).
 */
class CatalogoBaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermisoSeeder::class,
            RolSeeder::class,
            PlantelSeeder::class,
            PeriodoEscolarSeeder::class,
            GradoSeeder::class,
            UmbralRiesgoSeeder::class,
            ConfiguracionSeeder::class,
            SuperAdminSeeder::class,
        ]);
    }
}
