<?php

namespace App\Support\Reporte;

/**
 * PDF de una tabla, sin dependencias: carta horizontal, Helvetica (fuente base de
 * PDF, no se incrusta), encabezado de columnas repetido en cada página y
 * "Página x de n". El texto va en WinAnsi, así que acentos y ñ salen bien;
 * lo que no cabe en una columna se corta con "…".
 *
 * ponytail: solo texto y rectángulos; si algún día hacen falta imágenes o
 * gráficas, ahí sí conviene dompdf.
 */
final class Pdf
{
    private const ANCHO = 792;

    private const ALTO = 612;

    private const MARGEN = 36;

    private const FILA = 16;

    private const MARCA = [0.122, 0.227, 0.392]; // #1f3a64, marca-700 del front

    /** Anchos AFM de Helvetica y Helvetica-Bold, WinAnsi 32–255 (milésimas del tamaño). */
    private const W_NORMAL = '278,278,355,556,556,889,667,191,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,278,278,584,584,584,556,1015,667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,667,778,722,667,611,722,667,944,667,667,611,278,278,278,469,556,333,556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,556,556,333,500,278,556,500,722,500,500,500,334,260,334,584,350,556,350,222,556,333,1000,556,556,333,1000,667,333,1000,350,611,350,350,222,222,333,333,350,556,1000,333,1000,500,333,944,350,500,667,278,333,556,556,556,556,260,556,333,737,370,556,584,333,737,333,400,584,333,333,333,556,537,278,333,333,365,556,834,834,834,611,667,667,667,667,667,667,1000,722,667,667,667,667,278,278,278,278,722,722,778,778,778,778,778,584,778,722,722,722,722,667,667,611,556,556,556,556,556,556,889,500,556,556,556,556,278,278,278,278,556,556,556,556,556,556,556,584,611,556,556,556,556,500,556,500';

    private const W_NEGRITA = '278,333,474,556,556,889,722,238,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,333,333,584,584,584,611,975,722,722,722,722,667,611,778,722,278,556,722,611,833,722,778,667,778,722,667,611,722,667,944,667,667,611,333,278,333,584,556,333,556,611,556,611,556,333,611,611,278,278,556,278,889,611,611,611,611,389,556,333,611,556,778,556,556,500,389,280,389,584,350,556,350,278,556,500,1000,556,556,333,1000,667,333,1000,350,611,350,350,278,278,500,500,350,556,1000,333,1000,556,333,944,350,500,667,278,333,556,556,556,556,280,556,333,737,370,556,584,333,737,333,400,584,333,333,333,611,556,278,333,333,365,556,834,834,834,611,722,722,722,722,722,722,1000,722,667,667,667,667,278,278,278,278,722,722,778,778,778,778,778,584,778,722,722,722,722,667,667,611,556,556,556,556,556,556,889,556,556,556,556,556,278,278,278,278,611,611,611,611,611,611,611,584,611,611,611,611,611,556,611,556';

    /** @var array<string, int[]> */
    private static array $anchos = [];

    /**
     * @param  array{titulo: string, lineas: string[], columnas: array<int, array{titulo: string, ancho: float, alinear?: string, decimales?: int}>, filas: array<int, array<int, mixed>>, pie: string[]}  $r
     *                                                                                                                                                                                                         Celda: texto/número, o ['texto' => ..., 'color' => '#rrggbb'] para pintar un cuadrito de color antes.
     */
    public static function generar(array $r): string
    {
        // Los anchos de columna son proporciones: se escalan al ancho útil de la hoja
        $util = self::ANCHO - 2 * self::MARGEN;
        $total = array_sum(array_column($r['columnas'], 'ancho'));
        $cols = array_map(fn ($c) => ['ancho' => $c['ancho'] * $util / $total] + $c + ['alinear' => 'izq', 'decimales' => null], $r['columnas']);

        // Paginación: la primera página lleva el encabezado del reporte; la última, el pie
        $inicioTabla = self::ALTO - self::MARGEN - 30 - 13 * count($r['lineas']) - 10;
        $porPagina = fn (bool $primera) => intdiv((int) (($primera ? $inicioTabla : self::ALTO - self::MARGEN) - self::MARGEN - 24 - self::FILA), self::FILA);
        $paginas = [];
        $restantes = $r['filas'];
        do {
            $paginas[] = array_splice($restantes, 0, $porPagina($paginas === []));
        } while ($restantes);
        $espacioPie = 14 * count($r['pie']) + 10;
        if (count(end($paginas)) * self::FILA + $espacioPie > (count($paginas) === 1 ? $inicioTabla : self::ALTO - self::MARGEN) - self::MARGEN - 24 - self::FILA) {
            $paginas[] = []; // el pie no cabe: va solo en una hoja más
        }

        $contenidos = [];
        $n = 0;
        foreach ($paginas as $i => $filas) {
            $s = '';
            $y = self::ALTO - self::MARGEN;
            if ($i === 0) {
                $s .= self::texto(self::MARGEN, $y - 16, $r['titulo'], 16, true, self::MARCA);
                $y -= 30;
                foreach ($r['lineas'] as $linea) {
                    $s .= self::texto(self::MARGEN, $y - 9, $linea, 9, false, [0.31, 0.34, 0.40]);
                    $y -= 13;
                }
                $y -= 10;
            }
            if ($filas || $i === 0) {
                // Encabezado de columnas
                $s .= self::rect(self::MARGEN, $y - self::FILA - 2, $util, self::FILA + 2, self::MARCA);
                $x = self::MARGEN;
                foreach ($cols as $c) {
                    $s .= self::celda($x, $y - 13, $c, $c['titulo'], true, [1, 1, 1]);
                    $x += $c['ancho'];
                }
                $y -= self::FILA + 2;
            }
            foreach ($filas as $fila) {
                if ($n++ % 2) {
                    $s .= self::rect(self::MARGEN, $y - self::FILA, $util, self::FILA, [0.965, 0.969, 0.976]);
                }
                $x = self::MARGEN;
                foreach ($cols as $j => $c) {
                    $s .= self::celda($x, $y - 11.5, $c, $fila[$j] ?? '', false, [0.15, 0.17, 0.22]);
                    $x += $c['ancho'];
                }
                $y -= self::FILA;
            }
            if ($i === count($paginas) - 1) {
                $y -= 14;
                foreach ($r['pie'] as $linea) {
                    $s .= self::texto(self::MARGEN, $y - 9, $linea, 9, false, [0.31, 0.34, 0.40]);
                    $y -= 14;
                }
            }
            $pag = 'Página '.($i + 1).' de '.count($paginas);
            $s .= self::texto(self::ANCHO - self::MARGEN - self::ancho($pag, 8, false), self::MARGEN - 16, $pag, 8, false, [0.41, 0.44, 0.51]);
            $contenidos[] = $s;
        }

        return self::armar($contenidos);
    }

    /** Objetos: 1 catálogo, 2 páginas, 3–4 fuentes, y por página su /Page y su contenido. */
    private static function armar(array $contenidos): string
    {
        $fuente = fn ($nombre) => "<< /Type /Font /Subtype /Type1 /BaseFont /{$nombre} /Encoding /WinAnsiEncoding >>";
        $kids = [];
        $objetos = [1 => '<< /Type /Catalog /Pages 2 0 R >>', 2 => '', 3 => $fuente('Helvetica'), 4 => $fuente('Helvetica-Bold')];
        foreach ($contenidos as $s) {
            $pagina = count($objetos) + 1;
            $kids[] = "{$pagina} 0 R";
            $objetos[$pagina] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '.self::ANCHO.' '.self::ALTO.'] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents '.($pagina + 1).' 0 R >>';
            $objetos[$pagina + 1] = '<< /Length '.strlen($s)." >>\nstream\n{$s}\nendstream";
        }
        $objetos[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($kids).' >>';

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objetos as $num => $obj) {
            $offsets[$num] = strlen($pdf);
            $pdf .= "{$num} 0 obj\n{$obj}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= 'xref'."\n0 ".(count($objetos) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $o) {
            $pdf .= sprintf("%010d 00000 n \n", $o);
        }

        return $pdf.'trailer'."\n<< /Size ".(count($objetos) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }

    private static function celda(float $x, float $y, array $col, mixed $valor, bool $negrita, array $color): string
    {
        $s = '';
        $pad = 4;
        $disponible = $col['ancho'] - 2 * $pad;
        if (is_array($valor)) { // ['texto', 'color']: cuadrito de color + texto (el nivel nunca va solo por color)
            $s .= self::rect($x + $pad, $y - 0.5, 7, 7, self::rgb($valor['color']));
            $x += 11;
            $disponible -= 11;
            $valor = $valor['texto'];
        }
        if (is_float($valor) || (is_int($valor) && $col['decimales'] !== null)) {
            $valor = number_format($valor, $col['decimales'] ?? 2);
        }
        $texto = self::cortar((string) $valor, 8.5, $negrita, $disponible);
        $tx = match ($col['alinear']) {
            'der' => $x + $col['ancho'] - $pad - self::ancho($texto, 8.5, $negrita),
            'centro' => $x + ($col['ancho'] - self::ancho($texto, 8.5, $negrita)) / 2,
            default => $x + $pad,
        };

        return $s.self::texto($tx, $y, $texto, 8.5, $negrita, $color);
    }

    private static function texto(float $x, float $y, string $texto, float $tam, bool $negrita, array $color): string
    {
        $t = strtr(self::winAnsi($texto), ['\\' => '\\\\', '(' => '\\(', ')' => '\\)']);

        return sprintf("BT %.3F %.3F %.3F rg /%s %.1F Tf %.2F %.2F Td (%s) Tj ET\n", ...[...$color, $negrita ? 'F2' : 'F1', $tam, $x, $y, $t]);
    }

    private static function rect(float $x, float $y, float $w, float $h, array $color): string
    {
        return sprintf("%.3F %.3F %.3F rg %.2F %.2F %.2F %.2F re f\n", ...[...$color, $x, $y, $w, $h]);
    }

    private static function cortar(string $texto, float $tam, bool $negrita, float $max): string
    {
        if (self::ancho($texto, $tam, $negrita) <= $max) {
            return $texto;
        }
        while ($texto !== '' && self::ancho($texto.'…', $tam, $negrita) > $max) {
            $texto = mb_substr($texto, 0, -1);
        }

        return rtrim($texto).'…';
    }

    private static function ancho(string $texto, float $tam, bool $negrita): float
    {
        $tabla = self::$anchos[$negrita ? 'b' : 'n'] ??= array_map('intval', explode(',', $negrita ? self::W_NEGRITA : self::W_NORMAL));
        $suma = 0;
        foreach (str_split(self::winAnsi($texto)) as $c) {
            $suma += $tabla[ord($c) - 32] ?? 556;
        }

        return $suma * $tam / 1000;
    }

    private static function winAnsi(string $texto): string
    {
        return (string) iconv('UTF-8', 'Windows-1252//TRANSLIT', $texto);
    }

    private static function rgb(string $hex): array
    {
        return array_map(fn ($p) => hexdec($p) / 255, str_split(ltrim($hex, '#'), 2));
    }
}
