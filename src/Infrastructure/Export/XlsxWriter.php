<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\Export;

use RuntimeException;
use ZipArchive;

/**
 * Minimal .xlsx writer (one sheet, bold header, numbers as numbers, frozen first row, column widths).
 * Enough for reports opened in Excel or Google Sheets, without a spreadsheet library.
 */
final class XlsxWriter
{
    public static function available(): bool
    {
        return class_exists(ZipArchive::class);
    }

    /**
     * @param list<string>                      $headers
     * @param list<list<string|int|float|null>> $rows
     * @param list<int>                         $widths characters per column
     * @return string binary .xlsx
     */
    public static function build(string $sheetName, array $headers, array $rows, array $widths = []): string
    {
        $esc = static fn (string $v): string => htmlspecialchars(self::clean($v), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $col = static function (int $i): string {
            $name = '';
            for ($i++; $i > 0; $i = intdiv($i - 1, 26)) {
                $name = chr(65 + (($i - 1) % 26)) . $name;
            }
            return $name;
        };
        $cell = static function (int $c, int $r, string|int|float|null $value, int $style) use ($col, $esc): string {
            $ref = $col($c) . $r;
            if ($value === null || $value === '') {
                return "<c r=\"{$ref}\" s=\"{$style}\"/>";
            }
            if (is_int($value) || is_float($value)) {
                return "<c r=\"{$ref}\" s=\"" . ($style === 1 ? 1 : 2) . "\"><v>{$value}</v></c>";
            }
            return "<c r=\"{$ref}\" s=\"{$style}\" t=\"inlineStr\"><is><t xml:space=\"preserve\">" . $esc($value) . '</t></is></c>';
        };

        $xmlRows = '<row r="1">';
        foreach ($headers as $c => $h) {
            $xmlRows .= $cell($c, 1, $h, 1);
        }
        $xmlRows .= '</row>';
        foreach ($rows as $i => $row) {
            $r = $i + 2;
            $xmlRows .= "<row r=\"{$r}\">";
            foreach (array_values($row) as $c => $value) {
                $xmlRows .= $cell($c, $r, $value, 3);
            }
            $xmlRows .= '</row>';
        }
        $cols = '';
        foreach ($widths as $c => $w) {
            $n = $c + 1;
            $cols .= "<col min=\"{$n}\" max=\"{$n}\" width=\"{$w}\" customWidth=\"1\"/>";
        }
        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . ($cols !== '' ? "<cols>{$cols}</cols>" : '')
            . "<sheetData>{$xmlRows}</sheetData></worksheet>";
        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0.00"/></numFmts>'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFC6F432"/></patternFill></fill></fills>'
            . '<borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs>'
            . '<cellXfs count="4"><xf/><xf fontId="1" fillId="2" applyFont="1" applyFill="1"/><xf numFmtId="164" applyNumberFormat="1"/><xf applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf></cellXfs>'
            . '</styleSheet>';

        $tmp = wp_tempnam('cheppi-xlsx');
        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el archivo Excel.');
        }
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="' . $esc(mb_substr($sheetName, 0, 31)) . '" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->addFromString('xl/styles.xml', $styles);
        $zip->close();
        $data = (string) file_get_contents($tmp);
        @unlink($tmp);
        return $data;
    }

    /** Removes characters XML 1.0 does not allow; neutralises formulas. */
    private static function clean(string $v): string
    {
        $v = (string) preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $v);
        return preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v;
    }
}
