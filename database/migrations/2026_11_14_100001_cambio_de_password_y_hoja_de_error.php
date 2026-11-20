<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Contraseña temporal (cuentas creadas por importación): se cambia al primer inicio de sesión
        Schema::table('usuarios', function (Blueprint $table) {
            $table->boolean('debe_cambiar_password')->default(false)->after('activo');
        });
        // La plantilla de docentes tiene dos hojas: el error dice en cuál está
        Schema::table('importacion_errores', function (Blueprint $table) {
            $table->string('hoja', 40)->nullable()->after('importacion_id');
        });

        // Los orientadores también dan clase (Orientación Vocacional). En una base ya sembrada se les da
        // el permiso de pasar lista (en una nueva lo pone RolSeeder) y su fila en docentes, que es la
        // que va en un horario.
        $rolId = DB::table('roles')->where('nombre', 'orientador')->value('id');
        $permisoId = DB::table('permisos')->where('nombre', 'registrar_asistencia')->value('id');
        if ($rolId && $permisoId) {
            DB::table('rol_permiso')->insertOrIgnore(['rol_id' => $rolId, 'permiso_id' => $permisoId]);
        }
        $orientadores = DB::table('usuarios')
            ->join('roles', 'roles.id', '=', 'usuarios.rol_id')->where('roles.nombre', 'orientador')
            ->whereNotIn('usuarios.id', DB::table('docentes')->select('usuario_id'))
            ->pluck('usuarios.id');
        DB::table('docentes')->insert($orientadores->map(fn ($id) => ['usuario_id' => $id])->all());
    }

    public function down(): void
    {
        Schema::table('usuarios', fn (Blueprint $table) => $table->dropColumn('debe_cambiar_password'));
        Schema::table('importacion_errores', fn (Blueprint $table) => $table->dropColumn('hoja'));
    }
};
