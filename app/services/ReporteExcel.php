<?php

namespace App\Services;

use App\Core\Auth;

/**
 * Reportes de Excel con diseño corporativo uniforme:
 *   encabezado con empresa, título, periodo y fecha de generación,
 *   tarjetas de indicadores, tabla con encabezado de color, filas cebra,
 *   formatos de moneda/fecha/porcentaje, colores por estado, fila de totales,
 *   paneles congelados, autofiltro y configuración de impresión.
 *
 * Columnas: ['label' => 'Valor', 'key' => 'monto', 'type' => 'money|int|text|date|datetime|percent|badge',
 *            'width' => 16, 'total' => 'sum|count|avg|<texto>']
 */
class ReporteExcel
{
    public const PRIMARIO = 'FF3730A3';
    private const TEXTO = 'FF1E293B';
    private const GRIS = 'FF64748B';
    private const CEBRA = 'FFF6F7FB';
    private const LINEA = 'FFE2E8F0';

    private const FORMATOS = [
        'money'    => '"$" #,##0;[Red]-"$" #,##0;"$" 0',
        'int'      => '#,##0',
        'date'     => 'dd/mm/yyyy',
        'datetime' => 'dd/mm/yyyy h:mm AM/PM',
        'percent'  => '0.0%',
    ];

    /** Colores para la columna tipo badge y para resaltar filas. */
    private const TONOS = [
        'success' => ['FFD1FAE5', 'FF065F46'],
        'danger'  => ['FFFEE2E2', 'FF991B1B'],
        'warning' => ['FFFEF3C7', 'FF92400E'],
        'info'    => ['FFE0F2FE', 'FF075985'],
        'primary' => ['FFE0E7FF', 'FF3730A3'],
        'muted'   => ['FFF1F5F9', 'FF64748B'],
    ];

    private Xlsx $book;

    public function __construct(private string $titulo, private string $subtitulo = '')
    {
        $this->book = new Xlsx([
            'title'   => $titulo,
            'subject' => $subtitulo,
            'author'  => Auth::user()['nombre'] ?? 'APEX',
            'company' => empresa(),
        ]);
    }

    /**
     * Agrega una hoja con tabla.
     * $opts: kpis => [[label, valor, type, tono?]], rowTone => fn(array $row): ?string,
     *        badgeTone => fn(string $valor, array $row): ?string, titulo => título propio de la hoja,
     *        nota => texto al pie, vacio => mensaje si no hay filas
     */
    public function hoja(string $nombre, array $cols, array $rows, array $opts = []): static
    {
        $sh = $this->book->addSheet($nombre);
        $n = count($cols);
        $last = Xlsx::col($n - 1);
        $sh->widths(array_map(fn ($c) => $c['width'] ?? 14, $cols));
        $sh->footer(empresa() . ' · ' . ($opts['titulo'] ?? $this->titulo) . ' · APEX');

        // ---- Encabezado
        $band = ['fill' => self::PRIMARIO, 'color' => 'FFFFFFFF'];
        $sh->addRow(array_merge([['v' => mb_strtoupper(empresa()), 's' => $band + ['bold' => true, 'size' => 15, 'indent' => 1]]],
            array_fill(0, $n - 1, ['v' => null, 's' => $band])), 30);
        $sh->merge("A1:{$last}1");
        $sh->addRow(array_merge([['v' => $opts['titulo'] ?? $this->titulo, 's' => $band + ['size' => 11, 'indent' => 1]]],
            array_fill(0, $n - 1, ['v' => null, 's' => $band])), 20);
        $sh->merge("A2:{$last}2");
        $sh->addRow([['v' => $this->subtitulo, 's' => ['color' => self::TEXTO, 'bold' => true, 'size' => 10]]], 20);
        $sh->merge("A3:{$last}3");
        $sh->addRow([['v' => 'Generado el ' . date('d/m/Y \a \l\a\s h:i a') . ' por ' . (Auth::user()['nombre'] ?? 'APEX'),
            's' => ['color' => self::GRIS, 'italic' => true, 'size' => 9]]], 16);
        $sh->merge("A4:{$last}4");
        $sh->addRow([], 8);

        // ---- Indicadores (tarjetas): etiqueta arriba, valor grande abajo
        if (!empty($opts['kpis'])) {
            $kpis = array_slice($opts['kpis'], 0, max(1, intdiv($n, 2)));
            $span = max(1, intdiv($n, count($kpis)));
            $labels = $values = [];
            foreach ($kpis as $i => $k) {
                [$label, $valor, $tipo] = $k + [2 => 'text'];
                [$bg, $fg] = self::TONOS[$k[3] ?? 'primary'];
                $labels[] = ['v' => mb_strtoupper($label), 's' => ['fill' => $bg, 'color' => $fg, 'bold' => true, 'size' => 8, 'indent' => 1, 'v' => 'bottom']];
                $values[] = ['v' => $valor, 's' => ['fill' => $bg, 'color' => $fg, 'bold' => true, 'size' => 14, 'indent' => 1, 'h' => 'left',
                    'numFmt' => self::FORMATOS[$tipo] ?? null]];
                for ($j = 1; $j < $span; $j++) {
                    $labels[] = ['v' => null, 's' => ['fill' => $bg]];
                    $values[] = ['v' => null, 's' => ['fill' => $bg]];
                }
                $r = $sh->rowCount() + 1;
                if ($span > 1) {
                    $a = Xlsx::col($i * $span);
                    $b = Xlsx::col($i * $span + $span - 1);
                    $sh->merge("{$a}{$r}:{$b}{$r}")->merge("{$a}" . ($r + 1) . ":{$b}" . ($r + 1));
                }
            }
            $sh->addRow($labels, 18);
            $sh->addRow($values, 26);
            $sh->addRow([], 10);
        }

        // ---- Encabezado de la tabla
        $hdr = $sh->addRow(array_map(fn ($c) => ['v' => $c['label'], 's' => [
            'fill' => self::PRIMARIO, 'color' => 'FFFFFFFF', 'bold' => true, 'size' => 10, 'wrap' => true,
            'h' => in_array($c['type'] ?? 'text', ['money', 'int', 'percent'], true) ? 'right' : (($c['type'] ?? '') === 'badge' ? 'center' : 'left'),
            'border' => 'header', 'borderColor' => self::PRIMARIO, 'v' => 'top',
        ]], $cols), 40); // texto arriba: el botón del filtro queda abajo sin taparlo

        // ---- Datos
        if (!$rows) {
            $sh->addRow([['v' => $opts['vacio'] ?? 'No hay registros para los filtros seleccionados.', 's' => ['italic' => true, 'color' => self::GRIS, 'h' => 'center']]], 28);
            $sh->merge('A' . ($hdr + 1) . ":{$last}" . ($hdr + 1));
        }
        foreach ($rows as $i => $row) {
            $tono = isset($opts['rowTone']) ? ($opts['rowTone'])($row) : null;
            $cells = [];
            foreach ($cols as $c) {
                $tipo = $c['type'] ?? 'text';
                $v = $row[$c['key']] ?? null;
                $s = ['border' => 'bottom', 'borderColor' => self::LINEA, 'size' => 10, 'color' => self::TEXTO];
                if ($i % 2 === 1) {
                    $s['fill'] = self::CEBRA;
                }
                if ($tono === 'muted') {
                    $s['color'] = self::GRIS;
                    $s['strike'] = true;
                } elseif ($tono && isset(self::TONOS[$tono])) {
                    $s['color'] = self::TONOS[$tono][1];
                }
                switch ($tipo) {
                    case 'money':
                    case 'int':
                    case 'percent':
                        $v = $v === null || $v === '' ? null : (float) $v;
                        $s += ['numFmt' => self::FORMATOS[$tipo], 'h' => 'right'];
                        break;
                    case 'date':
                    case 'datetime':
                        $v = $v ? Xlsx::serial((string) $v) : null;
                        $s += ['numFmt' => self::FORMATOS[$tipo], 'h' => 'center'];
                        break;
                    case 'badge':
                        $bt = isset($opts['badgeTone']) ? ($opts['badgeTone'])((string) $v, $row) : null;
                        if ($bt && isset(self::TONOS[$bt])) {
                            [$s['fill'], $s['color']] = self::TONOS[$bt];
                            $s['bold'] = true;
                        }
                        $s += ['h' => 'center'];
                        $v = $v === null ? null : self::seguro((string) $v);
                        break;
                    default:
                        $v = $v === null ? null : self::seguro((string) $v);
                        $s['wrap'] = !empty($c['wrap']);
                }
                if (!empty($c['bold'])) {
                    $s['bold'] = true;
                }
                $cells[] = ['v' => $v, 's' => $s];
            }
            $sh->addRow($cells, 18);
        }
        $first = $hdr + 1;
        $lastData = $sh->rowCount();

        // ---- Totales (con fórmulas, así se recalculan si filtran o editan)
        if ($rows && array_filter($cols, fn ($c) => isset($c['total']))) {
            $cells = [];
            foreach ($cols as $j => $c) {
                $L = Xlsx::col($j);
                $s = ['bold' => true, 'fill' => 'FFE0E7FF', 'color' => self::PRIMARIO, 'border' => 'total', 'borderColor' => self::PRIMARIO, 'size' => 10];
                $t = $c['total'] ?? null;
                if (in_array($t, ['sum', 'count', 'avg'], true)) {
                    $fn = ['sum' => 'SUBTOTAL(109,', 'count' => 'SUBTOTAL(103,', 'avg' => 'SUBTOTAL(101,'][$t];
                    $val = match ($t) {
                        'sum'   => array_sum(array_map(fn ($r) => (float) ($r[$c['key']] ?? 0), $rows)),
                        'count' => count($rows),
                        'avg'   => array_sum(array_map(fn ($r) => (float) ($r[$c['key']] ?? 0), $rows)) / max(1, count($rows)),
                    };
                    $cells[] = ['v' => $val, 'f' => "{$fn}{$L}{$first}:{$L}{$lastData})",
                        's' => $s + ['numFmt' => $t === 'count' ? self::FORMATOS['int'] : (self::FORMATOS[$c['type'] ?? 'int'] ?? self::FORMATOS['int']), 'h' => 'right']];
                } else {
                    $cells[] = ['v' => $t, 's' => $s];
                }
            }
            $sh->addRow($cells, 22);
        }

        if (!empty($opts['nota'])) {
            $sh->addRow([], 8);
            $r = $sh->addRow([['v' => $opts['nota'], 's' => ['italic' => true, 'color' => self::GRIS, 'size' => 9, 'wrap' => true, 'v' => 'top']]], 30);
            $sh->merge("A{$r}:{$last}{$r}");
        }

        $sh->freeze($hdr)->printTitles($hdr, $hdr);
        if ($rows) {
            $sh->autoFilter("A{$hdr}:{$last}{$lastData}");
        }
        return $this;
    }

    public function descargar(string $archivo): never
    {
        $this->book->download($archivo . '.xlsx');
    }

    public function guardar(string $ruta): void
    {
        $this->book->save($ruta);
    }

    /**
     * El texto se guarda como cadena literal (inlineStr): Excel nunca lo evalúa como fórmula,
     * así que un nombre como "=HYPERLINK(...)" se muestra tal cual y no se ejecuta.
     * Solo se eliminan caracteres de control.
     */
    private static function seguro(string $v): string
    {
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $v);
    }
}
