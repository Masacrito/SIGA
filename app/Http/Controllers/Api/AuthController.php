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

        // El token del sistema web vence (el navegador es más expuesto); el de la app
        // móvil no, para no romper la cola offline del docente a media jornada.
        $nombreToken = $datos['dispositivo'] ?? 'api';
        $expira = $nombreToken === 'web'
            ? now()->addMinutes((int) config('sanctum.web_token_minutos', 480))
            : null;

        $token = $usuario->createToken($nombreToken, ['*'], $expira)->plainTextToken;

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

    /**
     * PUT /me/password — cambia la contraseña propia. Es lo único que puede hacer
     * (además de /me y /logout) quien trae una contraseña temporal. Cierra las
     * demás sesiones: quien conociera la contraseña anterior queda fuera.
     */
    public function cambiarPassword(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'password_actual' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'max:255', 'different:password_actual'],
        ], [
            'password_actual.current_password' => 'La contraseña actual no es correcta.',
            'password.different' => 'La contraseña nueva debe ser distinta de la actual.',
        ]);

        $usuario = $request->user();
        $usuario->forceFill(['password' => $datos['password'], 'debe_cambiar_password' => false])->save();
        $usuario->tokens()->whereKeyNot($usuario->currentAccessToken()->id)->delete();

        return response()->json($this->sesion($usuario->load('rol.permisos')));
    }

    /** Forma UsuarioSesion de openapi.yaml. */
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
            'debe_cambiar_password' => $usuario->debe_cambiar_password,
            'permisos' => $usuario->permisos(),
        ];
    }
}
