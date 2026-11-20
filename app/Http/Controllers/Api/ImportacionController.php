<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Grupo;
use App\Models\Importacion;
use App\Models\PeriodoEscolar;
use App\Support\Importacion\Alumnos;
use App\Support\Importacion\Calificaciones;
use App\Support\Importacion\Docentes;
use App\Support\Importacion\Plantilla;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** CU-14: carga masiva con plantillas de Excel que genera el propio SIGA. */
class ImportacionController extends Controller
{
    /** Plantilla para llenar: alumnos, docentes y orientadores, o calificaciones de un grupo en un parcial. */
    public function plantilla(Request $request): StreamedResponse
    {
        $request->validate([
            'tipo' => ['required', 'in:alumnos,calificaciones,docentes'],
            'grupo_id' => ['required_if:tipo,calificaciones', 'integer', 'exists:grupos,id'],
            'parcial' => ['required_if:tipo,calificaciones', 'integer', 'between:1,'.Calificaciones::PARCIALES],
        ]);
        $periodo = PeriodoEscolar::activo();
        if (! $periodo) {
            throw ValidationException::withMessages(['tipo' => 'No hay un periodo activo. Ábrelo en Catálogos.']);
        }

        if ($request->input('tipo') !== 'calificaciones') {
            return Plantilla::descargar(
                $request->input('tipo') === 'alumnos' ? Alumnos::plantilla() : Docentes::plantilla(),
                $this->nombre("plantilla {$request->input('tipo')} {$periodo->nombre}"),
            );
        }

        $grupo = Grupo::with('grado')->findOrFail($request->integer('grupo_id'));
        if ($grupo->periodo_id !== $periodo->id) {
            throw ValidationException::withMessages(['grupo_id' => 'El grupo no es del periodo activo.']);
        }
        $parcial = $request->integer('parcial');

        return Plantilla::descargar(
            Calificaciones::plantilla($grupo, $parcial),
            $this->nombre('calificaciones '.Alumnos::nombreGrupo($grupo)." parcial {$parcial} {$periodo->nombre}"),
        );
    }

    /** Últimas 30 cargas, la más reciente primero. */
    public function index(): JsonResponse
    {
        return response()->json(Importacion::with('usuario:id,nombre')->latest('id')->limit(30)->get()->map(fn ($i) => $this->forma($i)));
    }

    /**
     * Sube una plantilla llena. Todo o nada: si alguna fila tiene error no se guarda ninguna y la
     * importación queda "con_errores" con la lista de errores. Un archivo que no es plantilla
     * de SIGA da 422 y no se registra.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate(['archivo' => ['required', 'file', 'max:5120', 'extensions:xlsx']], [
            'archivo.extensions' => 'Sube la plantilla en formato Excel (.xlsx).',
        ]);
        $archivo = $request->file('archivo');
        [$meta, $libro] = Plantilla::leer($archivo);

        $tipo = $meta['tipo'] ?? '';
        [$filas, $errores, $credenciales] = match ($tipo) {
            'alumnos' => Alumnos::importar($libro),
            'calificaciones' => Calificaciones::importar($libro, $meta),
            'docentes' => Docentes::importar($libro, $meta),
            default => throw ValidationException::withMessages(['archivo' => 'Tipo de plantilla desconocido. Descarga la plantilla de nuevo.']),
        } + [2 => []];
        if ($filas === 0) {
            throw ValidationException::withMessages(['archivo' => 'La plantilla está vacía.']);
        }

        $importacion = DB::transaction(function () use ($request, $archivo, $tipo, $filas, $errores) {
            $importacion = Importacion::create([
                'usuario_id' => $request->user()->id,
                'periodo_id' => PeriodoEscolar::activo()?->id,
                'tipo' => $tipo,
                'nombre_archivo' => Str::limit($archivo->getClientOriginalName(), 250),
                'filas_procesadas' => $filas,
                'filas_error' => collect($errores)->unique(fn ($e) => ($e['hoja'] ?? '').$e['fila'])->count(),
                'estado' => $errores ? 'con_errores' : 'completado',
            ]);
            foreach (array_chunk($errores, 500) as $lote) {
                $importacion->errores()->insert(array_map(fn ($e) => [
                    'importacion_id' => $importacion->id,
                    'hoja' => $e['hoja'] ?? null,
                    'fila' => $e['fila'],
                    'columna' => $e['columna'],
                    'mensaje' => Str::limit($e['mensaje'], 250),
                ], $lote));
            }

            return $importacion;
        });

        // Las contraseñas temporales solo viajan en esta respuesta: no se guardan en claro ni se vuelven a mostrar
        return response()->json($this->forma($importacion->load('usuario:id,nombre'), true) + ['credenciales' => $credenciales], 201);
    }

    public function show(Importacion $importacion): JsonResponse
    {
        return response()->json($this->forma($importacion->load('usuario:id,nombre'), true));
    }

    private function forma(Importacion $i, bool $conErrores = false): array
    {
        return $i->only(['id', 'tipo', 'nombre_archivo', 'estado', 'filas_procesadas', 'filas_error']) + [
            'creada_en' => $i->created_at?->toIso8601String(),
            'usuario' => $i->usuario?->only(['id', 'nombre']),
        ] + ($conErrores ? ['errores' => $i->errores()->orderBy('hoja', 'desc')->orderBy('fila')->orderBy('id')->get(['hoja', 'fila', 'columna', 'mensaje'])] : []);
    }

    private function nombre(string $texto): string
    {
        return 'SIGA_'.Str::slug($texto, '_').'.xlsx';
    }
}
