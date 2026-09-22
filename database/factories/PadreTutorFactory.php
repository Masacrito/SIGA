<?php

namespace Database\Factories;

use App\Models\PadreTutor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PadreTutor>
 */
class PadreTutorFactory extends Factory
{
    protected $model = PadreTutor::class;

    public function definition(): array
    {
        return [
            'nombre' => fake()->firstName().' '.fake()->lastName().' '.fake()->lastName(),
            'telefono' => '961'.fake()->numerify('#######'),
            'correo' => null,
            // Sin 'push': no hay push a padres en esta fase (decisión 2026-09-18)
            'canal_preferido' => 'sms',
        ];
    }

    public function porCorreo(): static
    {
        return $this->state(fn () => [
            'correo' => fake()->unique()->safeEmail(),
            'canal_preferido' => 'email',
        ]);
    }
}
