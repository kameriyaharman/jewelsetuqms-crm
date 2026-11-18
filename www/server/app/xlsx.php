<?php
/** Minimal XLSX reader/writer using ZipArchive + SimpleXML (no Composer needed on shared hosting). */
/** Path of the first worksheet inside the zip. */
function xlsx_sheet_path(ZipArchive $z): string {
    $sheet = 'xl/worksheets/sheet1.xml';
    if (($wb = $z->getFromName('xl/workbook.xml')) !== false && ($rels = $z->getFromName('xl/_rels/workbook.xml.rels')) !== false) {
        $w = simplexml_load_string($wb);
        $first = $w->sheets->sheet[0] ?? null;
        if ($first) {
            $rid = (string)$first->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            $rx = simplexml_load_string($rels);
            foreach ($rx->Relationship as $r) if ((string)$r['Id'] === $rid) { $t = (string)$r['Target']; $sheet = str_starts_with($t, '/') ? ltrim($t, '/') : 'xl/' . $t; }
        }
    }
    return $sheet;
}
function xlsx_rel_target(string $base, string $target): string {
    if (str_starts_with($target, '/')) return ltrim($target, '/');
    $parts = explode('/', dirname($base));
    foreach (explode('/', $target) as $seg) { if ($seg === '..') array_pop($parts); elseif ($seg !== '.' && $seg !== '') $parts[] = $seg; }
    return implode('/', $parts);
}
function xlsx_rels(ZipArchive $z, string $part): array {
    $rp = dirname($part) . '/_rels/' . basename($part) . '.rels'; $out = [];
    if (($x = $z->getFromName($rp)) === false) return $out;
    foreach (simplexml_load_string($x)->Relationship as $r) $out[(string)$r['Id']] = ['type' => basename((string)$r['Type']), 'target' => xlsx_rel_target($part, (string)$r['Target'])];
    return $out;
}
/**
 * Read the first sheet keeping real row numbers (1-based) plus the pictures placed on it.
 * Returns ['rows' => [rowNo => [cells...]], 'images' => [['row' => 1-based top row, 'row2' => bottom row, 'col' => 0-based col, 'ext' => 'jpg', 'data' => bytes], ...]]
 */
function xlsx_read_full(string $file): array {
    $z = new ZipArchive();
    if ($z->open($file) !== true) throw new RuntimeException('Not a valid .xlsx file');
    $ss = [];
    if (($x = $z->getFromName('xl/sharedStrings.xml')) !== false) {
        $sx = simplexml_load_string($x);
        foreach ($sx->si as $si) {
            if (isset($si->t)) $ss[] = (string)$si->t;
            else { $t = ''; foreach ($si->r as $r) $t .= (string)$r->t; $ss[] = $t; }
        }
    }
    $sheet = xlsx_sheet_path($z);
    $xml = $z->getFromName($sheet);
    if ($xml === false) { $z->close(); throw new RuntimeException('Worksheet not found'); }
    $sx = simplexml_load_string($xml);
    $rows = [];
    foreach ($sx->sheetData->row as $row) {
        $r = []; $rn = (int)$row['r'];
        foreach ($row->c as $c) {
            $ref = (string)$c['r']; preg_match('/^([A-Z]+)(\d+)/', $ref, $m); $col = 0;
            foreach (str_split($m[1]) as $ch) $col = $col * 26 + (ord($ch) - 64);
            if (!$rn) $rn = (int)$m[2];
            $t = (string)$c['t']; $v = isset($c->v) ? (string)$c->v : '';
            if ($t === 's') $v = $ss[(int)$v] ?? '';
            elseif ($t === 'inlineStr') $v = (string)($c->is->t ?? '');
            if ($col <= 200) $r[$col - 1] = trim($v);
        }
        if ($r && array_filter($r, fn($x) => $x !== '')) { $max = max(array_keys($r)); $line = []; for ($k = 0; $k <= $max; $k++) $line[] = $r[$k] ?? ''; $rows[$rn] = $line; }
    }
    // pictures: sheet -> drawing -> anchors -> media
    $images = [];
    foreach (xlsx_rels($z, $sheet) as $rel) {
        if ($rel['type'] !== 'drawing' || ($dx = $z->getFromName($rel['target'])) === false) continue;
        $drels = xlsx_rels($z, $rel['target']);
        $d = simplexml_load_string($dx); $ns = $d->getNamespaces(true);
        $xdr = $ns['xdr'] ?? 'http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing';
        foreach ($d->children($xdr) as $anchor) {
            $from = $anchor->children($xdr)->from; if (!$from) continue;
            $to = $anchor->children($xdr)->to;
            $ax = $anchor->asXML();
            if (!preg_match('/r:embed="([^"]+)"/', $ax, $m) || empty($drels[$m[1]])) continue;
            $media = $drels[$m[1]]['target']; $data = $z->getFromName($media); if ($data === false) continue;
            $ext = strtolower(pathinfo($media, PATHINFO_EXTENSION)); if ($ext === 'jpeg') $ext = 'jpg';
            if (!in_array($ext, ['jpg', 'png', 'webp', 'gif'], true)) continue;
            $r1 = (int)$from->children($xdr)->row + 1;
            $images[] = ['row' => $r1, 'row2' => $to ? (int)$to->children($xdr)->row + 1 : $r1, 'col' => (int)$from->children($xdr)->col, 'ext' => $ext, 'data' => $data];
        }
    }
    $z->close();
    return ['rows' => $rows, 'images' => $images];
}
function xlsx_read(string $file): array { return array_values(xlsx_read_full($file)['rows']); }
function csv_read(string $file): array {
    $rows = []; $h = fopen($file, 'r');
    $first = fgets($h); rewind($h); $delim = substr_count((string)$first, ';') > substr_count((string)$first, ',') ? ';' : ',';
    while (($r = fgetcsv($h, 0, $delim, '"', '\\')) !== false) { if ($r === [null]) continue; $rows[] = array_map(fn($x) => trim((string)$x, " \t\xEF\xBB\xBF"), $r); }
    fclose($h); return $rows;
}
function excel_date($v): ?string {
    if ($v === '' || $v === null) return null;
    if (is_numeric($v) && $v > 20000 && $v < 80000) return date('Y-m-d', (int)(($v - 25569) * 86400));
    $t = strtotime(str_replace('/', '-', (string)$v)); return $t ? date('Y-m-d', $t) : null;
}
/** Write a simple single-sheet xlsx with a bold header row. */
function xlsx_write(array $rows, string $sheetName = 'Sheet1'): string {
    $esc = fn($s) => htmlspecialchars((string)$s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $colL = function ($i) { $s = ''; $i++; while ($i > 0) { $m = ($i - 1) % 26; $s = chr(65 + $m) . $s; $i = intdiv($i - 1, 26); } return $s; };
    $data = '';
    foreach ($rows as $ri => $r) {
        $data .= '<row r="' . ($ri + 1) . '">';
        foreach (array_values($r) as $ci => $v) {
            $ref = $colL($ci) . ($ri + 1); $st = $ri === 0 ? ' s="1"' : '';
            $data .= (is_int($v) || is_float($v)) ? "<c r=\"$ref\"$st><v>$v</v></c>" : "<c r=\"$ref\" t=\"inlineStr\"$st><is><t>" . $esc($v) . '</t></is></c>';
        }
        $data .= '</row>';
    }
    $cols = '<cols>' . implode('', array_map(fn($i) => '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="16" customWidth="1"/>', array_keys($rows[0] ?? []))) . '</cols>';
    $tmp = tempnam(sys_get_temp_dir(), 'xl'); $z = new ZipArchive(); $z->open($tmp, ZipArchive::OVERWRITE);
    $z->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
    $z->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
    $z->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="' . $esc($sheetName) . '" sheetId="1" r:id="rId1"/></sheets></workbook>');
    $z->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
    $z->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF0F3D45"/></patternFill></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs><cellXfs count="2"><xf/><xf fontId="1" fillId="2" applyFont="1" applyFill="1"/></cellXfs></styleSheet>');
    $z->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' . $cols . '<sheetData>' . $data . '</sheetData></worksheet>');
    $z->close(); $out = file_get_contents($tmp); unlink($tmp); return $out;
}
const IMPORT_COLS = ['Vendor', 'PO No', 'SKU', 'Image', 'Client Style Ref', 'Stone / Setting', 'Product', 'Order qty', 'Ready qty', 'QC scope', 'Metal', 'Plating', 'Weight', 'Length', 'Lock', 'Stamping', 'Requested date', 'Spec file'];
