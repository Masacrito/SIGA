<?php

namespace Database\Factories;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<Usuario>
 */
class UsuarioFactory extends Factory
{
    protected $model = Usuario::class;

    protected static ?string $password;

    public function definition(): array
    {
        return [
            'nombre' => fake()->firstName().' '.fake()->lastName().' '.fake()->lastName(),
            'username' => fake()->unique()->userName(),
            'password' => static::$password ??= Hash::make('password'),
            'correo' => fake()->unique()->safeEmail(),
            'activo' => true,
        ];
    }

    public function conRol(string $nombreRol): static
    {
        return $this->state(fn () => [
            'rol_id' => Rol::where('nombre', $nombreRol)->value('id'),
        ]);
    }

    public function docente(): static
    {
        return $this->conRol('docente');
    }

    public function orientador(): static
    {
        return $this->conRol('orientador');
    }

    public function administrador(): static
    {
        return $this->conRol('administrador');
    }

    public function inactivo(): static
    {
        return $this->state(fn () => ['activo' => false]);
    }
}
