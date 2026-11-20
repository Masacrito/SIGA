<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Con contraseña temporal (cuenta creada por importación) no se puede usar el
 * sistema hasta cambiarla con PUT /me/password. El cliente lo reconoce por
 * "codigo", no por el mensaje.
 */
class ExigirCambioPassword
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->debe_cambiar_password) {
            return response()->json([
                'message' => 'Cambia tu contraseña temporal para continuar.',
                'codigo' => 'debe_cambiar_password',
            ], 403);
        }

        return $next($request);
    }
}
