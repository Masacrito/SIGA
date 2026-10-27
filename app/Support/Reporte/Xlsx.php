<?php

namespace App\Support\Reporte;

/**
 * .xlsx de una hoja, sin dependencias: título y líneas arriba, encabezado en negritas
 * con filtro, números como números (Excel los suma y ordena), columnas con ancho
 * fijo y encabezado congelado. El ZIP se arma a mano sin compresión, así no
 * depende de la extensión zip de PHP (que XAMPP trae apagada).
 */
final class Xlsx
{
    /** @param array{titulo: string, lineas: string[], columnas: array<int, array{titulo: string, ancho: float}>, filas: array<int, array<int, mixed>>, pie: string[]} $r */
    public static function generar(array $r, string $hoja = 'Reporte'): string
    {
        $filasXml = '';
        $n = 0;
        $fila = function (array $celdas, int $estilo = 0) use (&$filasXml, &$n) {
            $n++;
            $xml = '';
            foreach (array_values($celdas) as $i => $v) {
                $ref = self::columna($i).$n;
                $v = is_array($v) ? $v['texto'] : $v;
                $s = $estilo ? " s=\"{$estilo}\"" : '';
                $xml .= is_int($v) || is_float($v)
                    ? "<c r=\"{$ref}\"{$s}><v>{$v}</v></c>"
                    : "<c r=\"{$ref}\"{$s} t=\"inlineStr\"><is><t xml:space=\"preserve\">".self::esc((string) $v).'</t></is></c>';
            }
            $filasXml .= "<row r=\"{$n}\">{$xml}</row>";
        };

        $fila([$r['titulo']], 2);
        foreach ($r['lineas'] as $l) {
            $fila([$l]);
        }
        $fila([]);
        $fila(array_column($r['columnas'], 'titulo'), 1);
        $encabezado = $n;
        foreach ($r['filas'] as $f) {
            $fila($f);
        }
        $ultima = max($n, $encabezado);
        $fila([]);
        foreach ($r['pie'] as $l) {
            $fila([$l]);
        }

        $ultimaCol = self::columna(count($r['columnas']) - 1);
        $anchos = '';
        foreach ($r['columnas'] as $i => $c) {
            $anchos .= '<col min="'.($i + 1).'" max="'.($i + 1).'" width="'.round($c['ancho'] / 5.5, 1).'" customWidth="1"/>';
        }

        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="'.$encabezado.'" topLeftCell="A'.($encabezado + 1).'" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            ."<cols>{$anchos}</cols><sheetData>{$filasXml}</sheetData>"
            ."<autoFilter ref=\"A{$encabezado}:{$ultimaCol}{$ultima}\"/></worksheet>";

        return self::zip([
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="'.self::esc(mb_substr($hoja, 0, 31)).'" sheetId="1" r:id="rId1"/></sheets><definedNames><definedName name="_xlnm._FilterDatabase" localSheetId="0" hidden="1">\''.self::esc(mb_substr($hoja, 0, 31))."'!\$A\${$encabezado}:\${$ultimaCol}\${$ultima}</definedName></definedNames></workbook>",
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
            // Estilos: 0 normal, 1 encabezado (negritas, blanco sobre azul marca), 2 título (negritas 14)
            'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="3"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font><font><b/><sz val="14"/><color rgb="FF1F3A64"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF1F3A64"/></patternFill></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/><xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>',
            'xl/worksheets/sheet1.xml' => $sheet,
        ]);
    }

    /** 0 → A, 25 → Z, 26 → AA. */
    private static function columna(int $i): string
    {
        $s = '';
        for ($i++; $i > 0; $i = intdiv($i - 1, 26)) {
            $s = chr(65 + ($i - 1) % 26).$s;
        }

        return $s;
    }

    private static function esc(string $s): string
    {
        // Quita caracteres de control que invalidan el XML
        return htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $s), ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /** ZIP "stored" (sin compresión): encabezado local + directorio central por archivo. */
    private static function zip(array $archivos): string
    {
        $datos = '';
        $central = '';
        foreach ($archivos as $nombre => $contenido) {
            $crc = crc32($contenido);
            $len = strlen($contenido);
            // versión, banderas (0x0800 = nombres UTF-8), sin compresión, hora 0, fecha 1980-01-01, crc, tamaños, largo del nombre
            $comun = pack('vvvvvVVVv', 20, 0x0800, 0, 0, 0x21, $crc, $len, $len, strlen($nombre));
            $central .= pack('Vv', 0x02014B50, 20).$comun.pack('vvvvVV', 0, 0, 0, 0, 0, strlen($datos)).$nombre;
            $datos .= pack('V', 0x04034B50).$comun.pack('v', 0).$nombre.$contenido;
        }

        return $datos.$central.pack('VvvvvVVv', 0x06054B50, 0, 0, count($archivos), count($archivos), strlen($central), strlen($datos), 0);
    }
}
