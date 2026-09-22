<?php

namespace Database\Seeders;

use App\Models\Grado;
use Illuminate\Database\Seeder;

class GradoSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['1', '2', '3'] as $nombre) {
            Grado::firstOrCreate(['nombre' => $nombre]);
        }
    }
}
