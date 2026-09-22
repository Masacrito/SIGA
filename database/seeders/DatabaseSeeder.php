<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Catálogo base siempre; datos de prueba solo en local y testing
     * (ver sección 7.1 del checkpoint).
     */
    public function run(): void
    {
        $this->call(CatalogoBaseSeeder::class);

        if (app()->environment(['local', 'testing'])) {
            $this->call(DemoSeeder::class);
        }
    }
}
