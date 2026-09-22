<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /** POST /login (CU-01) */
    public function login(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
            'dispositivo' => ['nullable', 'string', 'max:100'],
        ]);

        $usuario = Usuario::with('rol.permisos')->where('username', $datos['username'])->first();

        // Mismo mensaje para usuario inexistente, contraseña mala o cuenta inactiva:
        // no se le revela a nadie cuáles usernames existen.
        if (! $usuario || ! $usuario->activo || ! Hash::check($datos['password'], $usuario->password)) {
            return response()->json(['message' => 'Usuario o contraseña incorrectos.'], 401);
        }

        $token = $usuario->createToken($datos['dispositivo'] ?? 'api')->plainTextToken;

        return response()->json([
            'token' => $token,
            'usuario' => $this->sesion($usuario),
        ]);
    }

    /** POST /logout — revoca solo el token con el que se hizo la petición. */
    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    /** GET /me */
    public function me(Request $request): JsonResponse
    {
        return response()->json($this->sesion($request->user()->load('rol.permisos')));
    }

    /** Forma UsuarioSesion de openapi.yaml v1.3.0. */
    private function sesion(Usuario $usuario): array
    {
        return [
            'id' => $usuario->id,
            'nombre' => $usuario->nombre,
            'username' => $usuario->username,
            'correo' => $usuario->correo,
            'rol' => $usuario->rol?->nombre,
            'activo' => $usuario->activo,
            'es_super_admin' => $usuario->es_super_admin,
            'permisos' => $usuario->permisos(),
        ];
    }
}
