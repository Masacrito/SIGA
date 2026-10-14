<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** CU-10: cuentas de usuario. */
class UsuarioController extends Controller
{
    public function index(): JsonResponse
    {
        $usuarios = Usuario::with(['rol', 'docente'])->orderBy('nombre')->get();

        return response()->json($usuarios->map($this->forma(...)));
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $this->validar($request);

        $usuario = DB::transaction(function () use ($datos) {
            $usuario = Usuario::create($datos);
            $this->sincronizarRol($usuario, $datos);

            return $usuario;
        });

        return response()->json($this->forma($usuario->refresh()->load(['rol', 'docente'])), 201);
    }

    public function update(Request $request, Usuario $usuario): JsonResponse
    {
        $this->protegerSuperAdmin($request, $usuario);
        $datos = $this->validar($request, $usuario);

        if ($usuario->is($request->user()) && array_key_exists('activo', $datos) && ! $datos['activo']) {
            abort(422, 'No puedes desactivar tu propia cuenta.');
        }

        DB::transaction(function () use ($usuario, $datos) {
            $usuario->update($datos);
            $this->sincronizarRol($usuario, $datos);
            // Cuenta desactivada: sus sesiones abiertas dejan de servir en el acto
            if (! $usuario->activo) {
                $usuario->tokens()->delete();
            }
        });

        return response()->json($this->forma($usuario->load(['rol', 'docente'])));
    }

    /** Borrado lógico: activo = 0 y se revocan sus tokens. */
    public function destroy(Request $request, Usuario $usuario): Response
    {
        $this->protegerSuperAdmin($request, $usuario);
        abort_if($usuario->is($request->user()), 422, 'No puedes desactivar tu propia cuenta.');

        DB::transaction(function () use ($usuario) {
            $usuario->update(['activo' => false]);
            $usuario->tokens()->delete();
        });

        return response()->noContent();
    }

    private function validar(Request $request, ?Usuario $usuario = null): array
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'username' => ['required', 'string', 'max:60', 'alpha_dash', Rule::unique('usuarios', 'username')->ignore($usuario)],
            // Al editar, sin password se conserva la actual
            'password' => [$usuario ? 'nullable' : 'required', 'string', 'min:8', 'max:255'],
            'correo' => ['nullable', 'email', 'max:160'],
            'rol_id' => ['required', 'integer', 'exists:roles,id'],
            'numero_empleado' => ['nullable', 'string', 'max:20'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        if (blank($datos['password'] ?? null)) {
            unset($datos['password']);
        }

        return $datos;
    }

    /** Rol docente → su fila en docentes (los horarios cuelgan de ella). */
    /**
     * Rol docente → su fila en docentes (los horarios cuelgan de ella); si deja
     * de ser docente la fila se queda, sus horarios la referencian.
     * Si deja de ser orientador pierde sus grupos: si no, seguiría viendo sus alumnos.
     */
    private function sincronizarRol(Usuario $usuario, array $datos): void
    {
        $rol = Rol::whereKey($datos['rol_id'])->value('nombre');

        if ($rol !== 'orientador') {
            $usuario->gruposOrientados()->detach();
        }
        if ($rol === 'docente') {
            // Sin numero_empleado en la petición se conserva el que tenía
            $usuario->docente()->updateOrCreate([], array_intersect_key($datos, ['numero_empleado' => true]));
        }
    }

    /** Un administrador común no puede tocar a un súper admin (le cambiaría la contraseña). */
    private function protegerSuperAdmin(Request $request, Usuario $usuario): void
    {
        abort_if($usuario->es_super_admin && ! $request->user()->es_super_admin, 403, 'No tienes permiso para realizar esta acción.');
    }

    /** Forma Usuario de openapi.yaml. */
    private function forma(Usuario $u): array
    {
        return [
            'id' => $u->id,
            'nombre' => $u->nombre,
            'username' => $u->username,
            'correo' => $u->correo,
            'rol_id' => $u->rol_id,
            'rol' => $u->rol?->nombre,
            'numero_empleado' => $u->docente?->numero_empleado,
            'es_super_admin' => $u->es_super_admin,
            'activo' => $u->activo,
        ];
    }
}
