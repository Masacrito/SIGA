<?php

namespace Database\Seeders;

use App\Models\PeriodoEscolar;
use Illuminate\Database\Seeder;

class PeriodoEscolarSeeder extends Seeder
{
    public function run(): void
    {
        // PROVISIONAL: fechas a reemplazar por el calendario oficial del 2026 B.
        PeriodoEscolar::updateOrCreate(['nombre' => '2026 B'], [
            'fecha_inicio' => '2026-08-17',
            'fecha_fin' => '2027-01-22',
            'estado' => 'activo',
        ]);
    }
}
