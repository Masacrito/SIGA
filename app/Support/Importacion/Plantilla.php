<?php

namespace App\Support\Importacion;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Lo común a las plantillas de importación. Todo texto que viene de la BD o del usuario
 * se escribe como texto explícito: un nombre que empieza con "=" no debe volverse fórmula. Libro con:
 * - Una o más hojas de datos ("Datos"; "Personal" y "Clases"): encabezados en la fila 1, los datos desde la 2.
 * - "Instrucciones": qué va en cada columna.
 * - "Listas" (oculta): valores de las listas desplegables.
 * - "SIGA" (muy oculta): metadatos (tipo, versión y lo que cada tipo necesite),
 *   para reconocer la plantilla al subirla.
 */
class Plantilla
{
    const VERSION = '1';

    const MAX_FILAS = 5000;

    const NO_ES_PLANTILLA = 'El archivo no es una plantilla de SIGA. Descarga la plantilla desde esta pantalla y llénala.';

    const COLUMNAS_CAMBIARON = 'Las columnas de la plantilla cambiaron (se agregó, borró o movió alguna). Descarga la plantilla de nuevo.';

    private const AZUL = '1E3A5F';

    private const GRIS = '5B6B7F';

    /**
     * @param  array<string, array<string, array{obligatoria?: bool, ancho?: int, texto?: bool}>>  $hojas  título de la hoja => [encabezado => formato]
     * @param  list<string>  $instrucciones  una línea por renglón
     * @param  array<string, string|int>  $meta
     */
    public static function nueva(string $tipo, array $hojas, array $instrucciones, array $meta = []): Spreadsheet
    {
        $libro = new Spreadsheet;
        $libro->getProperties()->setCreator('SIGA')->setTitle("Plantilla SIGA: {$tipo}");

        foreach (array_keys($hojas) as $i => $titulo) {
            $hoja = ($i === 0 ? $libro->getActiveSheet() : $libro->createSheet())->setTitle($titulo);
            $c = 1;
            foreach ($hojas[$titulo] as $encabezado => $formato) {
                $letra = Coordinate::stringFromColumnIndex($c++);
                $hoja->setCellValueExplicit("{$letra}1", $encabezado, DataType::TYPE_STRING);
                $hoja->getStyle("{$letra}1")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => ($formato['obligatoria'] ?? false) ? self::AZUL : self::GRIS]],
                    'alignment' => ['wrapText' => true, 'vertical' => 'center'],
                ]);
                $hoja->getColumnDimension($letra)->setWidth($formato['ancho'] ?? 18);
                if ($formato['texto'] ?? false) {
                    // Texto: Excel no le quita ceros a la izquierda ni la vuelve 2.6E+13 (ni una hora en fracción)
                    $hoja->getStyle("{$letra}:{$letra}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
                }
            }
            $hoja->getRowDimension(1)->setRowHeight(32);
            $hoja->freezePane('A2');
        }

        $ayuda = $libro->createSheet()->setTitle('Instrucciones');
        foreach ($instrucciones as $i => $linea) {
            $ayuda->setCellValueExplicit('A'.($i + 1), $linea, DataType::TYPE_STRING);
        }
        $ayuda->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $ayuda->getColumnDimension('A')->setWidth(110);

        $libro->createSheet()->setTitle('Listas')->setSheetState(Worksheet::SHEETSTATE_HIDDEN);

        $siga = $libro->createSheet()->setTitle('SIGA')->setSheetState(Worksheet::SHEETSTATE_VERYHIDDEN);
        $fila = 1;
        foreach (['plantilla' => 'siga', 'tipo' => $tipo, 'version' => self::VERSION] + $meta as $clave => $valor) {
            $siga->setCellValue("A{$fila}", $clave);
            $siga->setCellValueExplicit("B{$fila}", (string) $valor, DataType::TYPE_STRING);
            $fila++;
        }

        $libro->setActiveSheetIndex(0);

        return $libro;
    }

    /** Lista desplegable en una columna de una hoja de datos; los valores van en la siguiente columna libre de la hoja oculta Listas. */
    public static function lista(Spreadsheet $libro, int $columna, array $valores, string $error, string $hoja = 'Datos'): void
    {
        $listas = $libro->getSheetByName('Listas');
        $letraLista = $listas->getCell('A1')->getValue() === null
            ? 'A'
            : Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($listas->getHighestDataColumn()) + 1);
        foreach (array_values($valores) as $i => $valor) {
            $listas->setCellValueExplicit($letraLista.($i + 1), (string) $valor, DataType::TYPE_STRING);
        }
        if (! $valores) {
            $listas->setCellValueExplicit("{$letraLista}1", '', DataType::TYPE_STRING); // aparta la columna
        }

        $letra = Coordinate::stringFromColumnIndex($columna);
        $v = $libro->getSheetByName($hoja)->getDataValidation("{$letra}2:{$letra}".(self::MAX_FILAS + 1));
        $v->setType(DataValidation::TYPE_LIST)
            ->setAllowBlank(true)
            ->setShowDropDown(true) // en PhpSpreadsheet true = mostrar la flecha
            ->setShowErrorMessage(true)
            ->setErrorStyle(DataValidation::STYLE_STOP)
            ->setErrorTitle('Valor no válido')
            ->setError($error)
            ->setFormula1("Listas!\${$letraLista}\$1:\${$letraLista}\$".max(1, count($valores)));
    }

    public static function descargar(Spreadsheet $libro, string $nombre): StreamedResponse
    {
        return response()->streamDownload(
            fn () => IOFactory::createWriter($libro, 'Xlsx')->save('php://output'),
            $nombre,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    /**
     * Abre un archivo subido y devuelve [metadatos, libro]. Si no es una plantilla de SIGA
     * vigente lanza 422 (no se registra la importación).
     *
     * @return array{0: array<string, string>, 1: Spreadsheet}
     */
    public static function leer(UploadedFile $archivo): array
    {
        try {
            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true);
            $libro = $reader->load($archivo->getRealPath());
        } catch (\Throwable) {
            throw ValidationException::withMessages(['archivo' => 'No se pudo leer el archivo. Guárdalo como "Libro de Excel (.xlsx)" e inténtalo de nuevo.']);
        }

        $meta = [];
        foreach ($libro->getSheetByName('SIGA')?->toArray(null, false, false) ?? [] as [$clave, $valor]) {
            $meta[(string) $clave] = (string) $valor;
        }
        if (($meta['plantilla'] ?? null) !== 'siga') {
            throw ValidationException::withMessages(['archivo' => self::NO_ES_PLANTILLA]);
        }
        if (($meta['version'] ?? null) !== self::VERSION) {
            throw ValidationException::withMessages(['archivo' => 'Esta plantilla es de una versión anterior. Descarga la plantilla de nuevo y pasa ahí los datos.']);
        }

        return [$meta, $libro];
    }

    /**
     * Filas con datos de una hoja: [número de fila de Excel => valores], sin las vacías. Con
     * $columnas revisa que los encabezados sean los de la plantilla. Lo que falle aquí es 422.
     *
     * @param  list<string>|null  $columnas
     * @return array<int, list<string>>
     */
    public static function filas(Spreadsheet $libro, string $hoja = 'Datos', ?array $columnas = null): array
    {
        $datos = $libro->getSheetByName($hoja);
        if (! $datos) {
            throw ValidationException::withMessages(['archivo' => self::NO_ES_PLANTILLA]);
        }
        if ($datos->getHighestDataRow() > self::MAX_FILAS + 1) {
            throw ValidationException::withMessages(['archivo' => "La hoja {$hoja} pasa de ".self::MAX_FILAS.' filas. Divídela en varios archivos.']);
        }

        $filas = [];
        foreach ($datos->toArray(null, true, false, false) as $i => $valores) {
            $valores = array_map(self::texto(...), $valores);
            if ($i === 0) {
                if ($columnas !== null && $valores !== $columnas) {
                    throw ValidationException::withMessages(['archivo' => self::COLUMNAS_CAMBIARON]);
                }
            } elseif (implode('', $valores) !== '') {
                $filas[$i + 1] = $valores;
            }
        }

        return $filas;
    }

    /** Valor de celda como texto limpio. Números enteros sin ".0" (una matrícula capturada como número). */
    private static function texto(mixed $valor): string
    {
        if (is_float($valor) && floor($valor) === $valor && abs($valor) < 1e15) {
            return sprintf('%.0f', $valor);
        }

        return trim(preg_replace('/\s+/u', ' ', (string) $valor));
    }
}
