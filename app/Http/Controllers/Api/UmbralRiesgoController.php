<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UmbralRiesgo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** CU-19: rangos del semáforo. */
class UmbralRiesgoController extends Controller
{
    /** ?periodo_id= ; sin él, los globales (periodo_id NULL). */
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->umbrales($request->integer('periodo_id') ?: null));
    }

    /**
     * Reemplaza los tres niveles de una vez: deben cubrir 0–100 sin huecos ni
     * traslapes (bajo < medio < alto). Por separado no se puede garantizar.
     */
    public function update(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'umbrales' => ['required', 'array', 'size:3'],
            'umbrales.*.id' => ['required', 'integer', 'distinct', 'exists:umbrales_riesgo,id'],
            'umbrales.*.porcentaje_min' => ['required', 'numeric', 'between:0,100'],
            'umbrales.*.porcentaje_max' => ['required', 'numeric', 'between:0,100'],
            'umbrales.*.faltas_consecutivas' => ['nullable', 'integer', 'min:1', 'max:60'],
            'umbrales.*.color_hex' => ['sometimes', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $actuales = UmbralRiesgo::findMany(array_column($datos['umbrales'], 'id'))->keyBy('id');
        $nuevos = collect($datos['umbrales'])
            ->sortBy(fn ($u) => array_search($actuales[$u['id']]->nivel, ['bajo', 'medio', 'alto']))
            ->values();

        $this->validarCobertura($nuevos->all(), $actuales->pluck('periodo_id')->unique()->count() === 1);

        DB::transaction(fn () => $nuevos->each(fn ($u) => $actuales[$u['id']]->update($u)));

        return response()->json($this->umbrales($actuales->first()->periodo_id));
    }

    private function validarCobertura(array $u, bool $mismoPeriodo): void
    {
        $error = match (true) {
            ! $mismoPeriodo => 'Los tres umbrales deben ser del mismo periodo.',
            (float) $u[0]['porcentaje_min'] !== 0.0 => 'El nivel bajo debe empezar en 0.',
            (float) $u[2]['porcentaje_max'] !== 100.0 => 'El nivel alto debe terminar en 100.',
            (float) $u[0]['porcentaje_max'] !== (float) $u[1]['porcentaje_min'],
            (float) $u[1]['porcentaje_max'] !== (float) $u[2]['porcentaje_min'] => 'Cada nivel debe empezar donde termina el anterior.',
            collect($u)->contains(fn ($x) => $x['porcentaje_min'] >= $x['porcentaje_max']) => 'En cada nivel, el mínimo debe ser menor que el máximo.',
            default => null,
        };

        if ($error) {
            throw ValidationException::withMessages(['umbrales' => $error]);
        }
    }

    private function umbrales(?int $periodoId)
    {
        return UmbralRiesgo::where('periodo_id', $periodoId)
            ->orderByRaw("CASE nivel WHEN 'bajo' THEN 1 WHEN 'medio' THEN 2 ELSE 3 END")
            ->get();
    }
}
