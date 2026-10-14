<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Catálogo base siempre. Datos de prueba: en testing los fijos de las
     * pruebas (PruebasSeeder); en local el demo del prototipo (DemoSeeder).
     */
    public function run(): void
    {
        $this->call(CatalogoBaseSeeder::class);

        if (app()->environment('testing')) {
            $this->call(PruebasSeeder::class);
        } elseif (app()->environment('local')) {
            $this->call(DemoSeeder::class);
        }
    }
}
