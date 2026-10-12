<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permiso;
use Illuminate\Http\JsonResponse;

class PermisoController extends Controller
{
    /** CU-10: catálogo de permisos. */
    public function index(): JsonResponse
    {
        return response()->json(Permiso::orderBy('id')->get());
    }
}
