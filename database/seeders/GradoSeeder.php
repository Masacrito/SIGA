<?php

namespace Database\Seeders;

use App\Models\Grado;
use Illuminate\Database\Seeder;

class GradoSeeder extends Seeder
{
    public function run(): void
    {
        // Semestres del bachillerato: nones de agosto a enero, pares de febrero a julio
        foreach (['1', '2', '3', '4', '5', '6'] as $nombre) {
            Grado::firstOrCreate(['nombre' => $nombre]);
        }
    }
}
