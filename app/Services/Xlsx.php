<?php

namespace App\Services;

use RuntimeException;
use ZipArchive;

/**
 * Minimal single-sheet .xlsx writer (Office Open XML) — no package needed. Bold, frozen header row.
 * ponytail: builds in memory; fine to ~100k rows. Stream rows to a temp file if exports grow past that.
 */
class Xlsx
{
    /** @param list<list<scalar|null>> $rows first row = header */
    public static function build(array $rows, string $sheet = 'Sheet1'): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('PHP zip extension is required for Excel export.');
        }
        $xml = '';
        foreach ($rows as $r => $cells) {
            $xml .= '<row r="'.($r + 1).'">';
            foreach (array_values($cells) as $c => $v) {
                $ref = self::col($c).($r + 1);
                $style = $r === 0 ? ' s="1"' : '';
                if (is_int($v) || is_float($v) || (is_string($v) && preg_match('/^-?(0|[1-9]\d{0,14})(\.\d+)?$/', $v))) {
                    $xml .= "<c r=\"$ref\"$style><v>$v</v></c>";
                } elseif ($v !== null && $v !== '') {
                    $xml .= "<c r=\"$ref\" t=\"inlineStr\"$style><is><t xml:space=\"preserve\">".htmlspecialchars((string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</t></is></c>';
                }
            }
            $xml .= '</row>';
        }
        $name = htmlspecialchars(mb_substr($sheet, 0, 31), ENT_XML1);
        $files = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="'.$name.'" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
            'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>',
            'xl/worksheets/sheet1.xml' => '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><sheetData>'.$xml.'</sheetData></worksheet>',
        ];
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;
        $zip->open($tmp, ZipArchive::OVERWRITE);
        foreach ($files as $path => $content) {
            $zip->addFromString($path, $content);
        }
        $zip->close();
        $bytes = file_get_contents($tmp);
        @unlink($tmp);

        return $bytes;
    }

    private static function col(int $i): string
    {
        $s = '';
        for ($i++; $i > 0; $i = intdiv($i - 1, 26)) {
            $s = chr(65 + ($i - 1) % 26).$s;
        }

        return $s;
    }
}
