<?php

namespace Database\Factories;

use App\Models\Alumno;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Alumno>
 */
class AlumnoFactory extends Factory
{
    protected $model = Alumno::class;

    public function definition(): array
    {
        return [
            // 14 dígitos: 2607 (generación) + 1035 (plantel) + consecutivo
            'matricula' => '26071035'.fake()->unique()->numerify('######'),
            'nombre' => fake()->firstName().' '.fake()->lastName().' '.fake()->lastName(),
            'activo' => true,
        ];
    }

    public function baja(): static
    {
        return $this->state(fn () => ['activo' => false]);
    }
}
