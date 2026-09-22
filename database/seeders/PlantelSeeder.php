<?php

namespace Database\Seeders;

use App\Models\Plantel;
use Illuminate\Database\Seeder;

class PlantelSeeder extends Seeder
{
    public function run(): void
    {
        // PROVISIONAL: confirmar nombre oficial y clave CCT con la institución.
        Plantel::updateOrCreate(['clave' => '07ECB0043I'], ['nombre' => 'COBACH Plantel 35']);
    }
}
