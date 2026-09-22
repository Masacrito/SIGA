<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uso en rutas: ->middleware('permiso:registrar_asistencia')
 *
 * Verifica el QUÉ (permiso del rol). El SOBRE QUIÉN (solo mis grupos, solo
 * los grupos que oriento) lo resuelven los middlewares/policies de
 * pertenencia de s5a–s7a.
 */
class VerificarPermiso
{
    public function handle(Request $request, Closure $next, string $permiso): Response
    {
        $usuario = $request->user();

        if (! $usuario) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        if (! $usuario->tienePermiso($permiso)) {
            return response()->json(['message' => 'No tienes permiso para realizar esta acción.'], 403);
        }

        return $next($request);
    }
}
