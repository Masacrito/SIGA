<?php

namespace Database\Seeders;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use RuntimeException;

class SuperAdminSeeder extends Seeder
{
    /**
     * Credenciales desde .env (ADMIN_USERNAME / ADMIN_PASSWORD), nunca en el código.
     * Fuera de local/testing, si falta la contraseña el seeder falla en vez de
     * crear un admin con contraseña por defecto.
     */
    public function run(): void
    {
        $username = env('ADMIN_USERNAME', 'superadmin');
        $password = env('ADMIN_PASSWORD');

        if (blank($password)) {
            if (! app()->environment(['local', 'testing'])) {
                throw new RuntimeException('Define ADMIN_PASSWORD en .env antes de sembrar el súper admin.');
            }
            $password = 'password';
            $this->command?->warn("ADMIN_PASSWORD no está definido: el súper admin '{$username}' usa 'password' (solo local).");
        }

        $usuario = Usuario::firstOrNew(['username' => $username]);

        if (! $usuario->exists) {
            $usuario->fill([
                'rol_id' => Rol::where('nombre', 'administrador')->value('id'),
                'nombre' => 'Súper administrador',
                'password' => $password,
                'activo' => true,
            ]);
        }

        $usuario->es_super_admin = true; // no es fillable a propósito
        $usuario->save();
    }
}
