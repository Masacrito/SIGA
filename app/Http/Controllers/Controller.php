<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

abstract class Controller
{
    /**
     * Respuesta de los endpoints que ya están declarados (y protegidos) pero
     * todavía sin lógica. Mientras tanto, móvil y web usan el mock de Prism.
     */
    protected function noImplementado(): JsonResponse
    {
        return response()->json([
            'message' => 'Endpoint no implementado todavía.',
            'endpoint' => request()->method().' /'.request()->path(),
        ], 501);
    }
}
