<?php

namespace App\Services;

/**
 * Generador de archivos Excel (.xlsx / Office Open XML) sin dependencias.
 * No requiere la extensión zip: el contenedor ZIP se arma a mano con zlib (gzdeflate).
 *
 * Soporta: varias hojas, estilos (fuentes, rellenos, bordes, alineación, formatos numéricos),
 * celdas combinadas, anchos de columna, alto de fila, paneles congelados, autofiltro,
 * configuración de impresión (horizontal, ajustar al ancho, títulos repetidos, pie de página)
 * y propiedades del documento.
 */
class Xlsx
{
    /** @var XlsxSheet[] */
    private array $sheets = [];
    private array $fonts = [];
    private array $fills = [];
    private array $borders = [];
    private array $numFmts = [];
    private array $xfs = [];
    private array $xfIndex = [];

    public function __construct(private array $props = [])
    {
        // Estilo 0 = predeterminado (obligatorio en Excel)
        $this->style([]);
    }

    public function addSheet(string $name): XlsxSheet
    {
        $name = mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', $name), 0, 31);
        $sheet = new XlsxSheet($this, $name, count($this->sheets) + 1);
        $this->sheets[] = $sheet;
        return $sheet;
    }

    /**
     * Registra un estilo y devuelve su índice.
     * Claves: bold, italic, strike, size, color, fill, border (none|thin|bottom|top|header|total),
     *         borderColor, numFmt, h (left|center|right), v (top|center|bottom), wrap, indent
     */
    public function style(array $s): int
    {
        ksort($s);
        $key = json_encode($s);
        if (isset($this->xfIndex[$key])) {
            return $this->xfIndex[$key];
        }
        $font = $this->idx($this->fonts, [
            'b' => !empty($s['bold']), 'i' => !empty($s['italic']), 'strike' => !empty($s['strike']),
            'sz' => $s['size'] ?? 10, 'color' => $s['color'] ?? 'FF1E293B',
        ]);
        $fill = isset($s['fill']) ? $this->idx($this->fills, ['color' => $s['fill']]) + 2 : 0;
        $border = $this->idx($this->borders, ['type' => $s['border'] ?? 'none', 'color' => $s['borderColor'] ?? 'FFE2E8F0']);
        $numFmt = 0;
        if (!empty($s['numFmt'])) {
            $numFmt = 164 + $this->idx($this->numFmts, ['code' => $s['numFmt']]);
        }
        $this->xfs[] = compact('font', 'fill', 'border', 'numFmt') + [
            'h' => $s['h'] ?? null, 'v' => $s['v'] ?? 'center', 'wrap' => !empty($s['wrap']), 'indent' => $s['indent'] ?? 0,
        ];
        return $this->xfIndex[$key] = count($this->xfs) - 1;
    }

    private function idx(array &$list, array $item): int
    {
        $i = array_search($item, $list, true);
        if ($i === false) {
            $list[] = $item;
            $i = count($list) - 1;
        }
        return $i;
    }

    // ------------------------------------------------------------------ salida

    public function download(string $filename): never
    {
        $data = $this->build();
        $filename = preg_replace('/[^A-Za-z0-9_.-]/', '_', $filename);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($data));
        header('Cache-Control: private, no-store');
        echo $data;
        exit;
    }

    public function save(string $path): void
    {
        file_put_contents($path, $this->build());
    }

    public function build(): string
    {
        if (!$this->sheets) {
            $this->addSheet('Hoja1');
        }
        $files = [
            '[Content_Types].xml'        => $this->contentTypes(),
            '_rels/.rels'                => $this->rootRels(),
            'docProps/core.xml'          => $this->core(),
            'docProps/app.xml'           => $this->app(),
            'xl/workbook.xml'            => $this->workbook(),
            'xl/_rels/workbook.xml.rels' => $this->workbookRels(),
        ];
        foreach ($this->sheets as $sh) {
            $files['xl/worksheets/sheet' . $sh->index . '.xml'] = $sh->xml();
        }
        $files['xl/styles.xml'] = $this->styles(); // al final: las hojas registran estilos al generarse
        return $this->zip($files);
    }

    private function contentTypes(): string
    {
        $o = '';
        foreach ($this->sheets as $sh) {
            $o .= '<Override PartName="/xl/worksheets/sheet' . $sh->index . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            . $o . '</Types>';
    }

    private function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            . '</Relationships>';
    }

    private function core(): string
    {
        $now = gmdate('Y-m-d\TH:i:s\Z');
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:title>' . self::x($this->props['title'] ?? 'Reporte') . '</dc:title>'
            . '<dc:subject>' . self::x($this->props['subject'] ?? '') . '</dc:subject>'
            . '<dc:creator>' . self::x($this->props['author'] ?? 'APEX') . '</dc:creator>'
            . '<cp:keywords>APEX cobros</cp:keywords>'
            . '<dc:description>Generado por APEX · Desarrollado por Ing. Antony Pérez</dc:description>'
            . '<cp:lastModifiedBy>' . self::x($this->props['author'] ?? 'APEX') . '</cp:lastModifiedBy>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:created>'
            . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:modified>'
            . '</cp:coreProperties>';
    }

    private function app(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties">'
            . '<Application>APEX</Application><Company>' . self::x($this->props['company'] ?? 'APEX') . '</Company></Properties>';
    }

    private function workbook(): string
    {
        $sheets = $names = '';
        foreach ($this->sheets as $sh) {
            $sheets .= '<sheet name="' . self::x($sh->name) . '" sheetId="' . $sh->index . '" r:id="rId' . $sh->index . '"/>';
            $q = "'" . str_replace("'", "''", $sh->name) . "'";
            if ($sh->filterRange) {
                $names .= '<definedName name="_xlnm._FilterDatabase" localSheetId="' . ($sh->index - 1) . '" hidden="1">' . self::x($q . '!' . self::abs($sh->filterRange)) . '</definedName>';
            }
            if ($sh->printTitles) {
                $names .= '<definedName name="_xlnm.Print_Titles" localSheetId="' . ($sh->index - 1) . '">' . self::x($q . '!$' . $sh->printTitles[0] . ':$' . $sh->printTitles[1]) . '</definedName>';
            }
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<bookViews><workbookView activeTab="0"/></bookViews>'
            . '<sheets>' . $sheets . '</sheets>'
            . ($names ? '<definedNames>' . $names . '</definedNames>' : '')
            . '</workbook>';
    }

    private function workbookRels(): string
    {
        $o = '';
        foreach ($this->sheets as $sh) {
            $o .= '<Relationship Id="rId' . $sh->index . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $sh->index . '.xml"/>';
        }
        $o .= '<Relationship Id="rId' . (count($this->sheets) + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $o . '</Relationships>';
    }

    private function styles(): string
    {
        $nf = '';
        foreach ($this->numFmts as $i => $f) {
            $nf .= '<numFmt numFmtId="' . (164 + $i) . '" formatCode="' . self::x($f['code']) . '"/>';
        }
        $fonts = '';
        foreach ($this->fonts as $f) {
            $fonts .= '<font>' . ($f['b'] ? '<b/>' : '') . ($f['i'] ? '<i/>' : '') . ($f['strike'] ? '<strike/>' : '')
                . '<sz val="' . $f['sz'] . '"/><color rgb="' . $f['color'] . '"/><name val="Calibri"/><family val="2"/></font>';
        }
        $fills = '<fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>';
        foreach ($this->fills as $f) {
            $fills .= '<fill><patternFill patternType="solid"><fgColor rgb="' . $f['color'] . '"/><bgColor indexed="64"/></patternFill></fill>';
        }
        $borders = '';
        foreach ($this->borders as $b) {
            $c = '<color rgb="' . $b['color'] . '"/>';
            $side = fn ($name, $style) => $style ? "<{$name} style=\"{$style}\">{$c}</{$name}>" : "<{$name}/>";
            [$l, $r, $t, $bo] = match ($b['type']) {
                'thin'   => ['thin', 'thin', 'thin', 'thin'],
                'bottom' => [null, null, null, 'thin'],
                'top'    => [null, null, 'thin', null],
                'header' => ['thin', 'thin', 'thin', 'medium'],
                'total'  => [null, null, 'medium', 'double'],
                default  => [null, null, null, null],
            };
            $borders .= '<border>' . $side('left', $l) . $side('right', $r) . $side('top', $t) . $side('bottom', $bo) . '<diagonal/></border>';
        }
        $xfs = '';
        foreach ($this->xfs as $x) {
            $al = '<alignment' . ($x['h'] ? ' horizontal="' . $x['h'] . '"' : '') . ' vertical="' . $x['v'] . '"'
                . ($x['wrap'] ? ' wrapText="1"' : '') . ($x['indent'] ? ' indent="' . (int) $x['indent'] . '"' : '') . '/>';
            $xfs .= '<xf numFmtId="' . $x['numFmt'] . '" fontId="' . $x['font'] . '" fillId="' . $x['fill'] . '" borderId="' . $x['border'] . '" xfId="0"'
                . ($x['numFmt'] ? ' applyNumberFormat="1"' : '') . ' applyFont="1"' . ($x['fill'] ? ' applyFill="1"' : '')
                . ($x['border'] ? ' applyBorder="1"' : '') . ' applyAlignment="1">' . $al . '</xf>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . ($nf ? '<numFmts count="' . count($this->numFmts) . '">' . $nf . '</numFmts>' : '')
            . '<fonts count="' . count($this->fonts) . '">' . $fonts . '</fonts>'
            . '<fills count="' . (count($this->fills) + 2) . '">' . $fills . '</fills>'
            . '<borders count="' . count($this->borders) . '">' . $borders . '</borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="' . count($this->xfs) . '">' . $xfs . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    /** Arma el contenedor ZIP (método deflate) sin la extensión zip. */
    private function zip(array $files): string
    {
        $data = $central = '';
        $offset = 0;
        $t = getdate();
        $dosTime = ($t['hours'] << 11) | ($t['minutes'] << 5) | intdiv($t['seconds'], 2);
        $dosDate = (($t['year'] - 1980) << 9) | ($t['mon'] << 5) | $t['mday'];
        foreach ($files as $name => $content) {
            $crc = crc32($content);
            $comp = gzdeflate($content, 6);
            $head = pack('VvvvvvVVVvv', 0x04034b50, 20, 0x0800, 8, $dosTime, $dosDate, $crc, strlen($comp), strlen($content), strlen($name), 0);
            $data .= $head . $name . $comp;
            $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0x0800, 8, $dosTime, $dosDate, $crc, strlen($comp),
                strlen($content), strlen($name), 0, 0, 0, 0, 0, $offset) . $name;
            $offset += strlen($head) + strlen($name) + strlen($comp);
        }
        return $data . $central . pack('VvvvvVVv', 0x06054b50, 0, 0, count($files), count($files), strlen($central), $offset, 0);
    }

    // ------------------------------------------------------------------ utilidades

    public static function x(string $s): string
    {
        $s = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $s) ?? '';
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    public static function col(int $n): string
    {
        $s = '';
        for ($n++; $n > 0; $n = intdiv($n - 1, 26)) {
            $s = chr(65 + ($n - 1) % 26) . $s;
        }
        return $s;
    }

    private static function abs(string $range): string
    {
        return preg_replace('/([A-Z]+)(\d+)/', '\$$1\$$2', $range);
    }

    /** Fecha 'Y-m-d [H:i:s]' -> número de serie de Excel. */
    public static function serial(string $date): ?float
    {
        $ts = strtotime($date . (strlen($date) === 10 ? ' 00:00:00' : '') . ' UTC');
        return $ts === false ? null : $ts / 86400 + 25569;
    }
}

class XlsxSheet
{
    private array $rows = [];
    private array $widths = [];
    private array $heights = [];
    private array $merges = [];
    private ?int $freezeRow = null;
    public ?string $filterRange = null;
    public ?array $printTitles = null;
    private bool $landscape = true;
    private string $footer = '';
    private int $maxCol = 0;

    public function __construct(private Xlsx $book, public string $name, public int $index) {}

    /** $cells: lista de valores o ['v' => valor, 's' => estilo(array|int), 'f' => fórmula] */
    public function addRow(array $cells, ?float $height = null): int
    {
        $this->rows[] = array_values($cells);
        $n = count($this->rows);
        $this->maxCol = max($this->maxCol, count($cells));
        if ($height) {
            $this->heights[$n] = $height;
        }
        return $n;
    }

    public function rowCount(): int { return count($this->rows); }
    public function widths(array $w): static { $this->widths = $w; return $this; }
    public function merge(string $range): static { $this->merges[] = $range; return $this; }
    public function freeze(int $row): static { $this->freezeRow = $row; return $this; }
    public function autoFilter(string $range): static { $this->filterRange = $range; return $this; }
    public function printTitles(int $from, int $to): static { $this->printTitles = [$from, $to]; return $this; }
    public function portrait(): static { $this->landscape = false; return $this; }
    public function footer(string $text): static { $this->footer = $text; return $this; }

    public function xml(): string
    {
        $cols = '';
        foreach ($this->widths as $i => $w) {
            $cols .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $w . '" customWidth="1"/>';
        }
        $rowsXml = '';
        foreach ($this->rows as $r => $cells) {
            $rn = $r + 1;
            $rowsXml .= '<row r="' . $rn . '"' . (isset($this->heights[$rn]) ? ' ht="' . $this->heights[$rn] . '" customHeight="1"' : '') . '>';
            foreach ($cells as $c => $cell) {
                $rowsXml .= $this->cell(Xlsx::col($c) . $rn, $cell);
            }
            $rowsXml .= '</row>';
        }
        $pane = $this->freezeRow
            ? '<pane ySplit="' . $this->freezeRow . '" topLeftCell="A' . ($this->freezeRow + 1) . '" activePane="bottomLeft" state="frozen"/><selection pane="bottomLeft" activeCell="A' . ($this->freezeRow + 1) . '" sqref="A' . ($this->freezeRow + 1) . '"/>'
            : '';
        $merges = '';
        if ($this->merges) {
            $merges = '<mergeCells count="' . count($this->merges) . '">';
            foreach ($this->merges as $m) {
                $merges .= '<mergeCell ref="' . $m . '"/>';
            }
            $merges .= '</mergeCells>';
        }
        $dim = 'A1:' . Xlsx::col(max(0, $this->maxCol - 1)) . max(1, count($this->rows));
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
            . '<dimension ref="' . $dim . '"/>'
            . '<sheetViews><sheetView workbookViewId="0" showGridLines="0"' . ($this->index === 1 ? ' tabSelected="1"' : '') . '>' . $pane . '</sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="15"/>'
            . ($cols ? '<cols>' . $cols . '</cols>' : '')
            . '<sheetData>' . $rowsXml . '</sheetData>'
            . ($this->filterRange ? '<autoFilter ref="' . $this->filterRange . '"/>' : '')
            . $merges
            . '<printOptions horizontalCentered="1"/>'
            . '<pageMargins left="0.4" right="0.4" top="0.5" bottom="0.6" header="0.3" footer="0.3"/>'
            . '<pageSetup paperSize="1" orientation="' . ($this->landscape ? 'landscape' : 'portrait') . '" fitToWidth="1" fitToHeight="0"/>'
            . '<headerFooter><oddFooter>' . Xlsx::x('&L&8' . $this->footer . '&R&8Página &P de &N') . '</oddFooter></headerFooter>'
            . '<ignoredErrors><ignoredError sqref="A1:' . Xlsx::col(max(0, $this->maxCol - 1)) . max(1, count($this->rows)) . '" numberStoredAsText="1"/></ignoredErrors>'
            . '</worksheet>';
    }

    private function cell(string $ref, mixed $cell): string
    {
        $style = 0;
        $formula = null;
        if (is_array($cell)) {
            $style = is_array($cell['s'] ?? null) ? $this->book->style($cell['s']) : (int) ($cell['s'] ?? 0);
            $formula = $cell['f'] ?? null;
            $cell = $cell['v'] ?? null;
        }
        $s = $style ? ' s="' . $style . '"' : '';
        if ($formula !== null) {
            return '<c r="' . $ref . '"' . $s . '><f>' . Xlsx::x($formula) . '</f>' . (is_numeric($cell) ? '<v>' . $cell . '</v>' : '') . '</c>';
        }
        if ($cell === null || $cell === '') {
            return $style ? '<c r="' . $ref . '"' . $s . '/>' : '';
        }
        if (is_int($cell) || is_float($cell)) {
            return '<c r="' . $ref . '"' . $s . '><v>' . (is_float($cell) ? rtrim(rtrim(sprintf('%.10F', $cell), '0'), '.') : $cell) . '</v></c>';
        }
        if (is_bool($cell)) {
            return '<c r="' . $ref . '"' . $s . ' t="b"><v>' . ($cell ? 1 : 0) . '</v></c>';
        }
        return '<c r="' . $ref . '"' . $s . ' t="inlineStr"><is><t xml:space="preserve">' . Xlsx::x((string) $cell) . '</t></is></c>';
    }
}
