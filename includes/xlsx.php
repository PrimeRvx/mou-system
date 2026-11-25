<?php
/**
 * includes/xlsx.php — สร้างไฟล์ .xlsx แบบง่ายโดยไม่ใช้ Composer (ต้องเปิดส่วนขยาย zip ของ PHP)
 */

/** กัน CSV/Excel formula injection: ค่าที่ขึ้นต้นด้วย = + - @ จะถูกนำหน้าด้วย ' */
function cell_safe($v): string
{
    $s = (string) $v;
    return ($s !== '' && strpos("=+-@\t\r", $s[0]) !== false && !is_numeric($s)) ? "'" . $s : $s;
}

function xlsx_col(int $i): string
{
    $s = '';
    for ($i++; $i > 0; $i = intdiv($i - 1, 26)) { $s = chr(65 + ($i - 1) % 26) . $s; }
    return $s;
}

/** ส่งไฟล์ xlsx ให้ดาวน์โหลด: $rows เป็นอาร์เรย์ของอาร์เรย์ (แถวแรกคือหัวตาราง) */
function xlsx_download(string $filename, string $sheetName, array $rows): void
{
    if (!class_exists('ZipArchive')) {
        http_response_code(500);
        exit('เซิร์ฟเวอร์ยังไม่เปิดส่วนขยาย zip ของ PHP — ใช้ส่งออก CSV แทน');
    }
    $x = static fn(string $t): string => htmlspecialchars($t, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><sheetFormatPr defaultRowHeight="18"/><cols>';
    $nc = count($rows[0] ?? []);
    for ($c = 0; $c < $nc; $c++) { $sheet .= '<col min="' . ($c + 1) . '" max="' . ($c + 1) . '" width="' . ($c === 2 ? 48 : 20) . '" customWidth="1"/>'; }
    $sheet .= '</cols><sheetData>';
    foreach ($rows as $r => $row) {
        $sheet .= '<row r="' . ($r + 1) . '">';
        foreach (array_values($row) as $c => $val) {
            $ref = xlsx_col($c) . ($r + 1);
            $style = $r === 0 ? ' s="1"' : '';
            if (is_int($val) || is_float($val)) {
                $sheet .= '<c r="' . $ref . '"' . $style . '><v>' . $val . '</v></c>';
            } else {
                $sheet .= '<c r="' . $ref . '" t="inlineStr"' . $style . '><is><t xml:space="preserve">' . $x(cell_safe($val)) . '</t></is></c>';
            }
        }
        $sheet .= '</row>';
    }
    $sheet .= '</sheetData></worksheet>';

    $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
    $zip = new ZipArchive();
    $zip->open($tmp, ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="' . $x(mb_substr(preg_replace('/[\\\\\/?*\[\]:]/u', '', $sheetName), 0, 31)) . '" sheetId="1" r:id="rId1"/></sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
    $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Tahoma"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Tahoma"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF0B2E6B"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>');
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
    $zip->close();

    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($tmp));
    readfile($tmp);
    @unlink($tmp);
    exit;
}

/** ส่งไฟล์ CSV (UTF-8 + BOM เพื่อให้ Excel อ่านภาษาไทยได้) */
function csv_download(string $filename, array $rows): void
{
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    foreach ($rows as $i => $row) {
        fputcsv($out, $i === 0 ? $row : array_map('cell_safe', $row));
    }
    fclose($out);
    exit;
}
